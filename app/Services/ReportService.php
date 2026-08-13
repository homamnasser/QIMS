<?php

namespace App\Services;

use App\Support\StaffScopeContext;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * محرّك التقارير: يقرأ التعريفات من `config/reports.php` ويبني منها الاستعلام
 * والتصفية والبحث والتنسيق. لا منطق خاص بكيان واحد هنا — إضافة تقرير جديد
 * تعديلٌ في ملف الإعداد وحده.
 *
 * الأداء: يُحمَّل من العلاقات ما تطلبه الحقول المختارة فقط (لا N+1)، والعدّ
 * يمرّ عبر withCount لا عبر جلب العناصر، والصفحات تُقطع في قاعدة البيانات،
 * والتصدير يتدفّق على دفعات فتبقى الذاكرة ثابتة.
 */
class ReportService
{
    private const ARABIC_DAYS = [
        'Sunday' => 'الأحد',
        'Monday' => 'الإثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
    ];

    private const PLACEHOLDER = '/\{([^}|]+)(?:\|([a-z_]+))?\}/u';

    /**
     * الكيانات التي يملك المستخدم صلاحية الاطّلاع عليها، بحقولها ومرشّحاتها.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(Authorizable $user): array
    {
        $catalog = [];

        foreach ((array) config('reports.entities', []) as $key => $entity) {
            if (! $this->allows($user, $entity['permission'] ?? null)) {
                continue;
            }

            $catalog[] = [
                'key' => $key,
                'label' => $entity['label'],
                'icon' => $entity['icon'] ?? 'document',
                'searchable' => ! empty($entity['search']),
                'fields' => array_values(array_map(
                    static fn (array $field): array => [
                        'key' => $field['key'],
                        'label' => $field['label'],
                        'default' => (bool) ($field['default'] ?? false),
                    ],
                    $this->availableFields($entity, $user)
                )),
                'filters' => $this->filterDefinitions($key, $entity),
            ];
        }

        return $catalog;
    }

    public function definition(string $key): ?array
    {
        $entity = config("reports.entities.{$key}");

        return is_array($entity) ? $entity : null;
    }

    public function allows(Authorizable $user, ?string $permission): bool
    {
        return $permission === null || $permission === '' || $user->can($permission);
    }

    /**
     * الحقول المسموح بها للمستخدم، مرتّبة حسب طلبه؛ وما لم يُعرَّف هنا يُهمَل،
     * فالتعريف نفسه هو قائمة السماح التي تمنع تسريب أعمدة غير مقصودة.
     *
     * @param  list<string>  $requested
     * @return array<string, array<string, mixed>>
     */
    public function selectFields(array $entity, Authorizable $user, array $requested): array
    {
        $available = $this->availableFields($entity, $user);

        $selected = [];
        foreach ($requested as $key) {
            if (isset($available[$key])) {
                $selected[$key] = $available[$key];
            }
        }

        if ($selected !== []) {
            return $selected;
        }

        $defaults = array_filter($available, static fn (array $field): bool => (bool) ($field['default'] ?? false));

        return $defaults !== [] ? $defaults : $available;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $filters
     */
    public function query(array $entity, array $fields, array $filters, ?string $search): Builder
    {
        /** @var Builder $query */
        $query = $entity['model']::query();

        foreach ($this->eagerLoads($fields) as $relation => $constraint) {
            $query->with([$relation => $constraint]);
        }

        foreach ($fields as $field) {
            if (isset($field['count'])) {
                $query->withCount($field['count']);
            }
        }

        $this->applyFilters($query, $entity, $filters);
        $this->applySearch($query, $entity, $search);

        [$column, $direction] = $entity['sort'] ?? ['id', 'desc'];

        return $query->orderBy($query->getModel()->qualifyColumn($column), $direction);
    }

    /**
     * @param  iterable<Model>  $models
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<array<string, string>>
     */
    public function rows(iterable $models, array $fields): array
    {
        $rows = [];

        foreach ($models as $model) {
            $rows[] = $this->row($model, $fields);
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    public function row(Model $model, array $fields): array
    {
        $row = [];

        foreach ($fields as $key => $field) {
            $row[$key] = $this->value($model, $field);
        }

        return $row;
    }

    /**
     * Excel وGoogle Sheets ينفّذان الخلية التي تبدأ بهذه الرموز كصيغة، والقيم
     * هنا يكتبها المستخدمون؛ فتُسبق بعلامة اقتباس لتبقى نصًّا.
     *
     * الأرقام وعلامة القيمة الفارغة «-» تمرّ كما هي: الرقم السالب ليس صيغة،
     * وتعليم كل خلية فارغة يفسد التقرير كلّه.
     */
    public function spreadsheetSafe(string $value): string
    {
        if ($value === '-' || preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function availableFields(array $entity, Authorizable $user): array
    {
        $fields = [];

        foreach ($entity['fields'] as $key => $field) {
            if (! $this->allows($user, $field['permission'] ?? null)) {
                continue;
            }

            $fields[$key] = [...$field, 'key' => $key, 'path' => $field['path'] ?? $key];
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filterDefinitions(string $entityKey, array $entity): array
    {
        $definitions = [];

        foreach ($entity['filters'] ?? [] as $filter) {
            $options = $filter['options'] ?? null;

            if ($options === 'distinct') {
                $options = $this->distinctOptions($entityKey, $entity, $filter);
            }

            $definitions[] = [
                'key' => $filter['key'],
                'label' => $filter['label'],
                'type' => $filter['type'],
                'options' => is_array($options) ? array_values($options) : null,
            ];
        }

        return $definitions;
    }

    /**
     * خيارات مشتقّة من البيانات نفسها. المفتاح يحمل نطاق المسجد لأن الاستعلام
     * يمرّ بالنطاق العام، فلا تُسرَّب قيم مسجد إلى موظّف مسجد آخر عبر الكاش.
     *
     * @return list<array{value: string, label: string}>
     */
    private function distinctOptions(string $entityKey, array $entity, array $filter): array
    {
        $column = $filter['path'];

        if (str_contains($column, '.')) {
            return [];
        }

        $scope = app(StaffScopeContext::class)->staff()?->mosque_id ?? 'all';

        return Cache::remember(
            "reports:options:{$entityKey}:{$filter['key']}:{$scope}",
            (int) config('reports.options_cache_ttl', 300),
            static fn (): array => $entity['model']::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->limit(500)
                ->pluck($column)
                ->map(static fn ($value): array => [
                    'value' => (string) $value,
                    'label' => (string) $value,
                ])
                ->all()
        );
    }

    /**
     * العلاقات التي تحتاجها الحقول المختارة وحدها.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, Closure>
     */
    private function eagerLoads(array $fields): array
    {
        /** @var array<string, Closure|null> $loads */
        $loads = [];

        foreach ($fields as $field) {
            if (isset($field['count'])) {
                continue;
            }

            if (isset($field['relation'])) {
                $relation = $field['relation'];
                $loads[$relation] ??= null;

                if (isset($field['order'])) {
                    $order = $field['order'];
                    $loads[$relation] = static fn ($query) => $query->orderBy($order);
                }

                foreach ($this->placeholders($field['template'] ?? '') as [$path]) {
                    if ($nested = $this->relationOf($path)) {
                        $loads["{$relation}.{$nested}"] ??= null;
                    }
                }

                continue;
            }

            foreach ($field['paths'] ?? [$field['path']] as $path) {
                if ($nested = $this->relationOf($path)) {
                    $loads[$nested] ??= null;
                }
            }
        }

        return array_map(
            static fn (?Closure $constraint): Closure => $constraint ?? static fn ($query) => $query,
            $loads
        );
    }

    private function relationOf(string $path): ?string
    {
        $segments = explode('.', $path);
        array_pop($segments);
        $segments = array_filter($segments, static fn (string $segment): bool => $segment !== '*');

        return $segments === [] ? null : implode('.', $segments);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $entity, array $filters): void
    {
        foreach ($entity['filters'] ?? [] as $filter) {
            $value = $filters[$filter['key']] ?? null;

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            if (($filter['cast'] ?? null) === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $isText = ($filter['type'] ?? 'select') === 'text';
            $operator = $isText ? 'like' : '=';
            $bound = $isText ? '%'.$value.'%' : $value;

            $this->whereOnPath(
                $query,
                $filter['path'],
                static fn ($builder, string $column) => $builder->where($column, $operator, $bound)
            );
        }
    }

    private function applySearch(Builder $query, array $entity, ?string $search): void
    {
        $term = trim((string) $search);

        if ($term === '' || empty($entity['search'])) {
            return;
        }

        $bound = '%'.$term.'%';

        $query->where(function (Builder $inner) use ($entity, $bound): void {
            foreach (array_values($entity['search']) as $index => $path) {
                $this->whereOnPath(
                    $inner,
                    $path,
                    static fn ($builder, string $column) => $builder->orWhere($column, 'like', $bound),
                    $index > 0
                );
            }
        });
    }

    /**
     * يطبّق شرطًا على عمود محلي أو على عمود خلف علاقة نقطية.
     */
    private function whereOnPath(Builder $query, string $path, Closure $apply, bool $or = false): void
    {
        $segments = explode('.', $path);
        $column = array_pop($segments);

        if ($segments === []) {
            $apply($query, $query->getModel()->qualifyColumn($column));

            return;
        }

        $query->{$or ? 'orWhereHas' : 'whereHas'}(
            implode('.', $segments),
            static fn ($related) => $apply($related, $related->getModel()->qualifyColumn($column))
        );
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function value(Model $model, array $field): string
    {
        if (isset($field['count'])) {
            return (string) ($model->{$field['count'].'_count'} ?? 0);
        }

        if (isset($field['relation'])) {
            $items = [];

            foreach ($model->{$field['relation']} ?? [] as $related) {
                $rendered = $this->render($field['template'] ?? '{name}', $related, $field['map'] ?? null);

                if ($rendered !== '') {
                    $items[] = $rendered;
                }
            }

            return $items === [] ? '-' : implode($field['separator'] ?? '، ', $items);
        }

        $parts = [];

        foreach ($field['paths'] ?? [$field['path']] as $path) {
            $part = $this->format(
                $this->scalar(data_get($model, $path)),
                $field['format'] ?? null,
                $field['map'] ?? null
            );

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return $parts === [] ? '-' : implode($field['separator'] ?? ' ', $parts);
    }

    /**
     * @param  array<string, string>|null  $map
     */
    private function render(string $template, mixed $related, ?array $map): string
    {
        $rendered = preg_replace_callback(
            self::PLACEHOLDER,
            function (array $matches) use ($related, $map): string {
                $value = $this->format(
                    $this->scalar(data_get($related, trim($matches[1]))),
                    $matches[2] ?? null,
                    $map
                );

                return $value === '' ? '-' : $value;
            },
            $template
        );

        return trim((string) $rendered);
    }

    /**
     * @return list<array{0: string, 1: string|null}>
     */
    private function placeholders(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $matches, PREG_SET_ORDER);

        return array_map(
            static fn (array $match): array => [trim($match[1]), $match[2] ?? null],
            $matches
        );
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $parts = array_filter(
                array_map(fn ($item): string => $this->scalar($item), $value),
                static fn (string $item): bool => $item !== ''
            );

            return implode('، ', $parts);
        }

        return $value === null ? '' : trim((string) $value);
    }

    /**
     * @param  array<string, string>|null  $map
     */
    private function format(string $value, ?string $format, ?array $map): string
    {
        if ($value === '') {
            return '';
        }

        $value = match ($format) {
            'date' => substr($value, 0, 10),
            'day_date' => $this->dayDate($value),
            default => $value,
        };

        return $map[$value] ?? $value;
    }

    private function dayDate(string $value): string
    {
        $date = substr($value, 0, 10);

        try {
            $day = self::ARABIC_DAYS[Carbon::parse($date)->format('l')] ?? '';
        } catch (\Throwable) {
            return $date;
        }

        return $day === '' ? $date : $day.' '.$date;
    }
}
