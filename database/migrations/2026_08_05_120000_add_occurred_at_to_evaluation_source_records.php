<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ الحدث لمصادر التقييم التي كانت تُصفّى بـ`created_at` وحده.
 *
 * محرّك التقييم يقرأ كل مصدر داخل نافذة الدورة. الغياب والتسميع والسبر تملك
 * تاريخ حدث يختاره المستخدم (`date` / `recorded_at`)، وملاحظات الإدارة تملك
 * `occurred_at` — بينما الاختبارات والإنذارات والملاحظات وسجل القراءة كانت
 * تُقارَن بلحظة كتابة الصف. النتيجة: علامة تُدخَل بعد انتهاء الدورة تسقط من
 * الحساب صامتاً رغم صحّتها، وإدخال متأخر مشروع (اختبار الخميس يُرصد الأحد)
 * يصبح مستحيلاً.
 *
 * الحقل يُعبّأ من `created_at` للصفوف القائمة، فسلوك أي سجل موجود لا يتغيّر
 * حرفياً؛ والاستعلامات تقرأ `COALESCE(occurred_at, created_at)` فتبقى الصفوف
 * التي تُكتب بلا تاريخ حدث محكومة بالسلوك القديم نفسه.
 */
return new class extends Migration
{
    private const TABLES = ['exams', 'warnings', 'notes', 'reading_improvements'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dateTime('occurred_at')->nullable()->after('id');
                $blueprint->index(['occurred_at'], $table.'_occurred_at_index');
            });

            DB::table($table)->update(['occurred_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_occurred_at_index');
                $blueprint->dropColumn('occurred_at');
            });
        }
    }
};
