<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Services\StudentIdentityConfirmationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * يقفل قسم النتيجة النهائية بأكمله خلف تأكيد هوية حديث.
 *
 * القفل على مستوى القسم لا على مستوى تحميل الشهادة وحده، فالنتيجة نفسها بيانات
 * شخصية لا يجوز أن يقرأها من يمسك الجهاز بعد صاحبه.
 */
class EnsureStudentIdentityConfirmed
{
    public function __construct(
        private readonly StudentIdentityConfirmationService $confirmations
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $student = $request->user();

        if (
            ! $student instanceof Student
            || ! $this->confirmations->isConfirmed($student)
        ) {
            // رمز خطأ مستقل: التطبيق يميّزه عن 403 الصلاحيات فيعرض شاشة تأكيد
            // الهوية بدل رسالة «غير مصرح» التي لا يملك الطالب حيالها فعلاً.
            return new JsonResponse([
                'code' => 403,
                'error_code' => 'IDENTITY_CONFIRMATION_REQUIRED',
                'message' => 'يجب تأكيد هويتك قبل عرض هذا القسم.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
