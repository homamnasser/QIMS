<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Support\StudentPushEvents;
use Illuminate\Support\Facades\Log;

class StudentPushService
{
    public function __construct(private readonly FcmClient $fcm) {}

    /**
     * المدخل الوحيد لكل إشعار طالب: يكتب صفّ الصندوق **الآن**، ويؤجّل الإرسال
     * إلى ما بعد تفريغ الاستجابة.
     *
     * الترتيب مقصود: الصف يُكتب في عملية الطلب نفسها فلا يعتمد السجل الدائم على
     * نداء مؤجَّل قد لا يُنفَّذ. أما الإرسال فبعد الاستجابة كي يبقى طلب FCM خارج
     * زمن انتظار الموظف، وبعد إتمام المعاملة فلا يصل إشعار عن سجل تراجع.
     *
     * ponytail: بلا طابور ولا عامل. جُرّب `QUEUE_CONNECTION=database` بمهمة لكل
     * طالب ثم أُلغي: مشكلته الوحيدة (سقوط ذيل قائمة النشر عند ~١٠٠ طالب بسبب
     * max_execution_time) لا وجود لها بأربعة مرشّحين، وكلفته عملية يجب إبقاؤها
     * حيّة. أعِده حين يقترب نشر دورة واحدة من مئة طالب — وحينها يكفي استبدال
     * الإغلاق أدناه بصنف مهمة.
     *
     * فشل الإشعار لا يُسقط الطلب ولا أمر artisan الذي كتب السجل: السجل حُفظ
     * فعلاً، والإشعار وسيلة إبلاغ لا جزء من المعاملة.
     */
    public static function queue(int $studentId, string $title, string $body, array $data = []): void
    {
        StudentNotification::create([
            'student_id' => $studentId,
            'title' => $title,
            'body' => $body,
            'route' => $data['route'] ?? null,
        ]);

        dispatch(function () use ($studentId, $title, $body, $data): void {
            try {
                app(self::class)->sendToStudent($studentId, $title, $body, $data);
            } catch (\Throwable $exception) {
                Log::warning('Student push failed', [
                    'student' => $studentId,
                    'title' => $title,
                    'message' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /** الإرسال المحض إلى أجهزة الطالب. */
    public function sendToStudent(int $studentId, string $title, string $body, array $data = []): void
    {
        // الوجهة موجودة في data أصلاً، فتكفي لاختيار أيقونة شريط الإشعارات بلا
        // تمرير وسيط إضافي عبر كل مستدعٍ.
        $android = StudentPushEvents::androidNotification($data['route'] ?? null);

        DeviceToken::query()
            ->where('tokenable_type', Student::class)
            ->where('tokenable_id', $studentId)
            ->get()
            ->each(function (DeviceToken $device) use ($title, $body, $data, $android): void {
                if (! $this->fcm->send($device->token, $title, $body, $data, $android)) {
                    $device->delete();
                }
            });
    }
}
