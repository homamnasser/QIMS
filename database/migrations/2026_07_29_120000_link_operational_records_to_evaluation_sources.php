<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('memorizations', 'course_id')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->foreignId('course_id')->nullable()->after('giver');
            });
        }
        if (! Schema::hasColumn('memorizations', 'circle_id')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->foreignId('circle_id')->nullable()->after('course_id');
            });
        }
        if (! Schema::hasColumn('memorizations', 'course_date_id')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->foreignId('course_date_id')->nullable()->after('circle_id');
            });
        }
        if (! Schema::hasColumn('memorizations', 'record_type')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->string('record_type', 24)
                    ->default('memorization')
                    ->after('course_date_id');
            });
        }
        if (! Schema::hasColumn('memorizations', 'recorded_at')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->dateTime('recorded_at')->nullable()->after('record_type');
            });
        }

        $this->addMemorizationForeignKey(
            'course_id',
            'memorization_course_fk',
            'courses'
        );
        $this->addMemorizationForeignKey(
            'circle_id',
            'memorization_circle_fk',
            'circles'
        );
        $this->addMemorizationForeignKey(
            'course_date_id',
            'memorization_course_date_fk',
            'course_dates'
        );

        if (! Schema::hasIndex('memorizations', 'memorization_student_fk_index')) {
            Schema::table('memorizations', function (Blueprint $table) {
                // MySQL needs another student-leading index before the legacy
                // composite index can be replaced because it backs the student FK.
                $table->index('student', 'memorization_student_fk_index');
            });
        }
        if (Schema::hasIndex('memorizations', 'memorizations_student_page_number_unique')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->dropUnique('memorizations_student_page_number_unique');
            });
        }
        if (! Schema::hasIndex('memorizations', 'memorization_session_page_type_unique')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->unique(
                    ['student', 'course_date_id', 'page_number', 'record_type'],
                    'memorization_session_page_type_unique'
                );
            });
        }
        if (Schema::hasIndex('memorizations', 'memorization_student_fk_index')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->dropIndex('memorization_student_fk_index');
            });
        }
        if (! Schema::hasIndex('memorizations', 'memorization_student_circle_date_index')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->index(
                    ['student', 'circle_id', 'recorded_at'],
                    'memorization_student_circle_date_index'
                );
            });
        }

        if (! Schema::hasColumn('warnings', 'deduction_points')) {
            Schema::table('warnings', function (Blueprint $table) {
                $table->decimal('deduction_points', 5, 2)
                    ->default(1)
                    ->after('description');
            });
        }
        if (! Schema::hasIndex('warnings', 'warnings_student_date_index')) {
            Schema::table('warnings', function (Blueprint $table) {
                $table->index(['student', 'created_at'], 'warnings_student_date_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('warnings', 'warnings_student_date_index')) {
            Schema::table('warnings', function (Blueprint $table) {
                $table->dropIndex('warnings_student_date_index');
            });
        }
        if (Schema::hasColumn('warnings', 'deduction_points')) {
            Schema::table('warnings', function (Blueprint $table) {
                $table->dropColumn('deduction_points');
            });
        }

        if (Schema::hasIndex('memorizations', 'memorization_student_circle_date_index')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->dropIndex('memorization_student_circle_date_index');
            });
        }
        if (! Schema::hasIndex('memorizations', 'memorization_student_fk_index')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->index('student', 'memorization_student_fk_index');
            });
        }
        if (Schema::hasIndex('memorizations', 'memorization_session_page_type_unique')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->dropUnique('memorization_session_page_type_unique');
            });
        }
        if (! Schema::hasIndex('memorizations', 'memorizations_student_page_number_unique')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->unique(
                    ['student', 'page_number'],
                    'memorizations_student_page_number_unique'
                );
            });
        }
        if (Schema::hasIndex('memorizations', 'memorization_student_fk_index')) {
            Schema::table('memorizations', function (Blueprint $table) {
                $table->dropIndex('memorization_student_fk_index');
            });
        }

        foreach ([
            'memorization_course_date_fk',
            'memorization_circle_fk',
            'memorization_course_fk',
        ] as $foreignKey) {
            if (Schema::hasForeignKey('memorizations', $foreignKey)) {
                Schema::table('memorizations', function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey);
                });
            }
        }
        $columns = collect([
            'course_id',
            'circle_id',
            'course_date_id',
            'record_type',
            'recorded_at',
        ])->filter(fn (string $column) => Schema::hasColumn('memorizations', $column));
        if ($columns->isNotEmpty()) {
            Schema::table('memorizations', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns->all());
            });
        }
    }

    private function addMemorizationForeignKey(
        string $column,
        string $constraint,
        string $referencedTable
    ): void {
        if (Schema::hasForeignKey('memorizations', $constraint)) {
            return;
        }

        Schema::table('memorizations', function (Blueprint $table) use (
            $column,
            $constraint,
            $referencedTable
        ) {
            $table->foreign($column, $constraint)
                ->references('id')
                ->on($referencedTable)
                ->nullOnDelete();
        });
    }
};
