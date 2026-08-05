<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * حذف جدول ملاحظات السلوك الإداري.
 *
 * الجدول لم يملك مسار إنشاء واحداً في التطبيق: لا نقطة نهاية، ولا خدمة تكتب
 * فيه، ولا شاشة. كان له مسار «اعتماد» لسجلات لا سبيل لإنشائها، وكاتبه الوحيد
 * بذرة العرض. ومعيار الإدارة كان يجمع حسومه تحت اسم
 * `legacy_observation_deductions` إلى جانب الإنذارات — وهي المصدر الوحيد الذي
 * يملك واجهة كاملة، وقد صارت الآن المصدر الوحيد للمعيار.
 *
 * الحسم نفسه يُعبَّر عنه بإنذار بنقاطه وتاريخه، فلا فقدان لأي قدرة تشغيلية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('administration_behavior_observations');

        // الصلاحية كانت تحرس مسار الاعتماد وحده؛ بلا مسار تصبح خانة تُؤشَّر في
        // شاشة الأدوار ولا تمنح شيئاً.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()
            ->where('name', 'إدارة تقييم الإدارة')
            ->where('guard_name', 'web')
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * يعيد بناء الجدول فارغاً كما كان. البيانات لا تُستعاد — ولم تكن تُنشأ من
     * التطبيق أصلاً، والحسومات المكافئة محفوظة في `warnings`.
     */
    public function down(): void
    {
        Schema::create('administration_behavior_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_candidate_id');
            $table->foreignId('evaluation_period_id')->nullable();
            $table->string('context_type', 40)->default('general');
            $table->text('description');
            $table->decimal('deduction_points', 5, 2);
            $table->dateTime('occurred_at');
            $table->foreignId('observed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign(
                'evaluation_candidate_id',
                'admin_behavior_candidate_fk'
            )->references('id')->on('evaluation_candidates')->cascadeOnDelete();
            $table->foreign(
                'evaluation_period_id',
                'admin_behavior_period_fk'
            )->references('id')->on('evaluation_periods')->nullOnDelete();
            $table->index(
                ['evaluation_candidate_id', 'status', 'occurred_at'],
                'admin_observations_candidate_index'
            );
        });
    }
};
