<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * واجهة نظام التقارير. الكيانات والحقول والمرشّحات تُقرأ من `config/reports.php`،
 * والوصول محكوم بصلاحية الكيان فوق صلاحية «عرض التقارير» على المسار كلّه،
 * ثم بنطاق المسجد المطبَّق تلقائيًا على الاستعلامات.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => 'تم جلب كيانات التقارير بنجاح',
            'data' => $this->reports->catalog($request->user()),
        ]);
    }

    public function show(Request $request, string $entity): JsonResponse
    {
        [$definition, $fields, $query] = $this->prepare($request, $entity);

        $perPage = min(
            max((int) $request->query('per_page', (string) config('reports.default_per_page', 25)), 1),
            (int) config('reports.max_per_page', 200)
        );

        $page = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'code' => 200,
            'message' => 'تم توليد التقرير بنجاح',
            'data' => [
                'entity' => $entity,
                'label' => $definition['label'],
                'columns' => array_values(array_map(
                    static fn (array $field): array => [
                        'key' => $field['key'],
                        'label' => $field['label'],
                    ],
                    $fields
                )),
                'rows' => $this->reports->rows($page->items(), $fields),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    public function export(Request $request, string $entity): BinaryFileResponse|StreamedResponse
    {
        [$definition, $fields, $query] = $this->prepare($request, $entity);

        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $filename = 'تقرير_'.$definition['label'].'_'.now()->format('Y-m-d').'.'.$format;

        if ($format === 'xlsx') {
            return Excel::download(
                new ReportExport($this->reports, $query, $fields),
                $filename,
                ExcelWriter::XLSX
            );
        }

        return $this->streamCsv($query, $fields, $filename);
    }

    /**
     * مسار CSV يبقى إلى جانب Excel لأنه وحده يكتب على التدفّق مباشرة: ملف
     * xlsx يُبنى في الذاكرة قبل إرساله، فالتقارير الضخمة تُصدَّر csv.
     *
     * @param  array<string, array<string, mixed>>  $fields
     */
    private function streamCsv(Builder $query, array $fields, string $filename): StreamedResponse
    {
        $chunk = (int) config('reports.export_chunk', 1000);

        return response()->streamDownload(function () use ($query, $fields, $chunk): void {
            $handle = fopen('php://output', 'w');

            // Excel لا يتعرّف على UTF-8 دون هذه العلامة فتظهر العربية مشوّهة.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(
                static fn (array $field): string => $field['label'],
                array_values($fields)
            ));

            // lazyById يقطع النتائج بالمعرّف فلا تُحمَّل الجداول الكبيرة كاملةً
            // في الذاكرة مهما بلغ حجم التقرير.
            $query->lazyById($chunk)->each(function (Model $model) use ($handle, $fields): void {
                fputcsv($handle, array_map(
                    fn (string $value): string => $this->reports->spreadsheetSafe($value),
                    array_values($this->reports->row($model, $fields))
                ));
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>, 2: Builder}
     */
    private function prepare(Request $request, string $entity): array
    {
        $validated = $request->validate([
            'fields' => ['sometimes', 'nullable'],
            'fields.*' => ['string', 'max:100'],
            'filters' => ['sometimes', 'array'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('reports.max_per_page', 200)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'format' => ['sometimes', 'in:xlsx,csv'],
        ]);

        $definition = $this->reports->definition($entity);

        abort_if($definition === null, 404, 'التقرير المطلوب غير معرَّف.');
        abort_unless(
            $this->reports->allows($request->user(), $definition['permission'] ?? null),
            403,
            'لا تملك صلاحية الاطّلاع على هذا التقرير.'
        );

        $requested = $validated['fields'] ?? [];
        if (is_string($requested)) {
            $requested = array_filter(array_map('trim', explode(',', $requested)));
        }

        $fields = $this->reports->selectFields($definition, $request->user(), array_values((array) $requested));

        $query = $this->reports->query(
            $definition,
            $fields,
            (array) ($validated['filters'] ?? []),
            $validated['q'] ?? null
        );

        return [$definition, $fields, $query];
    }
}
