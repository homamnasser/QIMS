<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentIdentityConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentIdentityController extends Controller
{
    public function __construct(
        private readonly StudentIdentityConfirmationService $confirmations
    ) {}

    /**
     * يفتح قسم النتيجة النهائية بعد تأكيد الهوية.
     *
     * يقبل المعرّف باسم المستخدم أو بالرقم الذاتي الحالي؛ الرقم الذاتي وحده لا
     * يصلح معرّف دخول أساسياً لأن الطالب غير الملتحق بأي حلقة لا يملكه، لكنه
     * صالح هنا لأن التأكيد يجري لحساب معروف مسبقاً من رمز الجلسة.
     */
    public function confirm(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! $this->confirmations->matchesOwnIdentity(
            $student,
            $credentials['identifier'],
            $credentials['password']
        )) {
            // فشل التأكيد يلغي أي إذن سابق: محاولة خاطئة تقفل القسم بدل أن
            // تتركه مفتوحاً بإذن قديم ما زال سارياً.
            $this->confirmations->revoke($student);

            return $this->noStore(response()->json([
                'code' => 403,
                'error_code' => 'IDENTITY_CONFIRMATION_FAILED',
                'message' => 'تعذر تأكيد هويتك؛ تحقق من المعرّف وكلمة المرور ثم أعد المحاولة.',
                'data' => null,
            ], 403));
        }

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'تم تأكيد هويتك؛ يمكنك الآن عرض نتائجك وتحميل شهاداتك.',
            'data' => ['expires_in' => $this->confirmations->grant($student)],
        ]));
    }

    /**
     * يقفل القسم فور خروج الطالب منه أو انتقال التطبيق إلى الخلفية، بدل انتظار
     * انتهاء مهلة الإذن.
     */
    public function lock(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $this->confirmations->revoke($student);

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'تم قفل القسم.',
            'data' => null,
        ]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private'
        );
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
