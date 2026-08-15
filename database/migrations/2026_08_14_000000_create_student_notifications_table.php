<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صندوق إشعارات الطالب: سجل دائم لكل ما أُبلغ به.
 *
 * الإشعار الحيّ يضيع بلا أثر إن كان الهاتف مطفأً أو الإذن مرفوضاً أو الرمز
 * ميتاً (يُحذف صفّه عند ردّ 404 من FCM). وشاشات الوجهة تعرض الحالة الراهنة لا
 * الأحداث: علامة صُحّحت مرتين تظهر صفّاً واحداً. هذا الجدول يحفظ ما أُعلن ومتى.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_notifications', function (Blueprint $table) {
            $table->id();
            // student_id لا morphs: الصندوق شاشة في تطبيق الطالب، ولا معنى
            // لصندوق للموظف قبل أن تصله إشعارات أصلاً.
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('body');
            // الوجهة قابلة للإفراغ: إشعار بلا شاشة تفتحها ممكن، وبطاقته تُعرض
            // بلا نقرة بدل أن تفتح مساراً لا يعرفه هذا الإصدار.
            $table->string('route')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // الاستعلام الوحيد: صفوف طالب واحد، الأحدث أولاً.
            $table->index(['student_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_notifications');
    }
};
