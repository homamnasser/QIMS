<?php

namespace App\Exports;

use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * ملف Excel لأي تقرير: الأعمدة والقيم تأتي من نفس محرّك التقارير الذي يغذّي
 * المعاينة، فلا يفترق المصدَّر عمّا رآه المستخدم.
 *
 * FromQuery يقطع الاستعلام دفعةً بدفعة بدل تحميله كاملاً، والترتيب في
 * ReportService على المفتاح الأساسي فالتقطيع مستقرّ لا يكرّر صفًّا ولا يسقطه.
 */
class ReportExport implements FromQuery, WithCustomChunkSize, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison
{
    /**
     * @param  array<string, array<string, mixed>>  $fields
     */
    public function __construct(
        private readonly ReportService $reports,
        private readonly Builder $builder,
        private readonly array $fields
    ) {}

    public function query(): Builder
    {
        return $this->builder;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(
            static fn (array $field): string => $field['label'],
            array_values($this->fields)
        );
    }

    /**
     * @param  Model  $row
     * @return list<string>
     */
    public function map(mixed $row): array
    {
        return array_map(
            fn (string $value): string => $this->reports->spreadsheetSafe($value),
            array_values($this->reports->row($row, $this->fields))
        );
    }

    public function chunkSize(): int
    {
        return (int) config('reports.export_chunk', 1000);
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            // التقارير عربية بالكامل، فالورقة تُفتح من اليمين كما تُقرأ.
            AfterSheet::class => static function (AfterSheet $event): void {
                $event->getSheet()->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
