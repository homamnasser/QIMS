<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * إعادة ضبط الحدود الدنيا بعد مراجعتها:
 * - المعلم يرى دورات التقييم وتقييمات الطلاب النهائية ليصل إلى بطاقة التقييم من الويب.
 * - المشرف الميداني يُحصر في الحضور وارتباطاته، وتُسحب منه قدرة «الإشراف الميداني الكامل».
 * - الطالب والإدارة والإشراف يحصلون على «تسجيل الدخول» و«تسجيل الخروج».
 *
 * التطبيق يمر بأمر `roles:sync-minimums` لا بمنطق مكرّر هنا؛ فمصدر الحقيقة
 * الوحيد هو `config/roles.php`، وأي تعديل لاحق عليه يُطبَّق بتشغيل الأمر نفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('roles:sync-minimums');
    }

    public function down(): void
    {
        // الحدود الدنيا تُشتق من الإعدادات لا من التاريخ؛ التراجع يكون بإرجاع
        // `config/roles.php` ثم إعادة تشغيل الأمر.
    }
};
