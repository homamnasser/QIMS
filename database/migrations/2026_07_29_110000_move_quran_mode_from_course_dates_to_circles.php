<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('circles', 'quran_mode')) {
            Schema::table('circles', function (Blueprint $table) {
                $table->string('quran_mode', 24)->default('none')->after('course_id');
            });
        }
        if (! $this->hasIndex('circles', 'circles_quran_mode_index')) {
            Schema::table('circles', function (Blueprint $table) {
                $table->index('quran_mode', 'circles_quran_mode_index');
            });
        }

        if (! Schema::hasColumn('evaluation_candidate_enrollments', 'quran_mode_snapshot')) {
            Schema::table('evaluation_candidate_enrollments', function (Blueprint $table) {
                $table->string('quran_mode_snapshot', 24)
                    ->default('none')
                    ->after('circle_name_snapshot');
            });
        }

        if ($this->hasForeignKey(
            'course_dates',
            'course_dates_evaluation_period_id_foreign',
            'evaluation_period_id'
        )) {
            Schema::table('course_dates', function (Blueprint $table) {
                $table->dropForeign(['evaluation_period_id']);
            });
        }
        if ($this->hasIndex('course_dates', 'course_dates_period_activity_index')) {
            Schema::table('course_dates', function (Blueprint $table) {
                $table->dropIndex('course_dates_period_activity_index');
            });
        }

        $obsoleteColumns = collect([
            'evaluation_period_id',
            'activity_type',
            'counts_for_recitation',
        ])->filter(fn (string $column) => Schema::hasColumn('course_dates', $column))->all();

        if ($obsoleteColumns !== []) {
            Schema::table('course_dates', function (Blueprint $table) use ($obsoleteColumns) {
                $table->dropColumn($obsoleteColumns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('course_dates', function (Blueprint $table) {
            $table->foreignId('evaluation_period_id')
                ->nullable()
                ->after('course_id')
                ->constrained('evaluation_periods')
                ->nullOnDelete();
            $table->string('activity_type', 40)->default('general')->after('session_date');
            $table->boolean('counts_for_recitation')
                ->default(false)
                ->after('counts_for_attendance');
            $table->index(
                ['evaluation_period_id', 'activity_type'],
                'course_dates_period_activity_index'
            );
        });

        Schema::table('evaluation_candidate_enrollments', function (Blueprint $table) {
            $table->dropColumn('quran_mode_snapshot');
        });

        Schema::table('circles', function (Blueprint $table) {
            $table->dropIndex('circles_quran_mode_index');
            $table->dropColumn('quran_mode');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition) => ($definition['name'] ?? null) === $index);
    }

    private function hasForeignKey(string $table, string $foreignKey, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $definition) => ($definition['name'] ?? null) === $foreignKey
                || in_array($column, $definition['columns'] ?? [], true)
        );
    }
};
