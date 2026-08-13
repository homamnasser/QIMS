<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\EvaluationResult;
use App\Models\EvaluationRun;
use App\Models\Student;
use App\Services\Evaluation\CertificateService;
use App\Services\Evaluation\EvaluationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificates,
        private readonly EvaluationAccessService $access,
    ) {}

    public function issue(Request $request, EvaluationResult $result): JsonResponse
    {
        $result->loadMissing('run.cycle');
        abort_unless($this->access->canApproveCycle($request->user(), $result->run->cycle), 403);

        return response()->json([
            'message' => 'تم إصدار الشهادة وحفظ بصمتها الرقمية.',
            'data' => $this->certificates->issue($result, $request->user()),
        ], 201);
    }

    public function issueBatch(Request $request, EvaluationRun $run): JsonResponse
    {
        $run->loadMissing(['cycle', 'results']);
        abort_unless($this->access->canApproveCycle($request->user(), $run->cycle), 403);

        $publishedResults = $run->results->where('status', 'published');
        if ($publishedResults->isEmpty()) {
            return response()->json([
                'code' => 422,
                'error_code' => 'NO_PUBLISHED_RESULTS',
                'message' => 'لا توجد نتائج منشورة في هذا التشغيل؛ يجب اعتماد النتائج ونشرها قبل إصدار الشهادات.',
                'data' => null,
            ], 422);
        }

        $issued = [];
        foreach ($publishedResults as $result) {
            $issued[] = $this->certificates->issue($result, $request->user());
        }

        return response()->json([
            'message' => 'تم إصدار شهادات النتائج المنشورة ('.count($issued).' شهادة).',
            'data' => ['count' => count($issued), 'certificates' => $issued],
        ]);
    }

    public function download(Request $request, Certificate $certificate): StreamedResponse
    {
        $certificate->loadMissing('result.run.cycle');
        abort_unless(
            $this->access->canViewCycle($request->user(), $certificate->result->run->cycle),
            403
        );
        abort_unless(
            $certificate->status === 'issued',
            410,
            'هذه الشهادة لم تعد سارية؛ تم إلغاؤها أو استُبدلت بإصدار أحدث.'
        );
        $this->certificates->assertFileIntegrity($certificate);

        return Storage::disk($certificate->file_disk)->download(
            $certificate->file_path,
            $certificate->serial_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * تسليم الشهادة للطالب مشروط بتأكيد هوية إضافي: رمز الجلسة وحده لا يكفي،
     * فالجهاز قد يبقى مفتوحاً بيد غير صاحبه. لذلك يعيد الطالب إدخال معرّفه
     * (اسم المستخدم أو رقمه الذاتي الحالي) وكلمة مروره مع الطلب نفسه.
     *
     * التأكيد جزء من طلب التحميل لا خطوة سابقة له، فلا تبقى نافذة زمنية
     * مفتوحة بين التأكيد والتسليم، ولا يوجد مسار تحميل ثانٍ بلا تأكيد.
     */
    public function studentDownload(Request $request, int $certificateId): StreamedResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(
            $this->confirmsOwnIdentity(
                $student,
                $credentials['identifier'],
                $credentials['password']
            ),
            403,
            'تعذر تأكيد هويتك؛ تحقق من المعرّف وكلمة المرور ثم أعد المحاولة.'
        );

        $certificate = Certificate::query()
            ->whereKey($certificateId)
            ->where('status', 'issued')
            ->whereHas(
                'result.candidate',
                fn ($query) => $query->where('student_id', $student->id)
            )
            ->firstOrFail();
        $this->certificates->assertFileIntegrity($certificate);

        return Storage::disk($certificate->file_disk)->download(
            $certificate->file_path,
            $certificate->serial_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * تتم المقارنة مع أعمدة الطالب المصادق عليه نفسه، لا ببحث في جدول الطلاب.
     * لذلك لا يفتح معرّف طالب آخر شهادة هذا الحساب، ولا تتحول الواجهة إلى وسيلة
     * لتخمين معرّفات الطلاب.
     *
     * الرقم الذاتي يقبله من يملكه فقط؛ والطالب غير الملتحق بأي حلقة لا رقم ذاتي
     * له، فيؤكد باسم المستخدم وحده ولا يُحرم من شهادته.
     */
    private function confirmsOwnIdentity(
        Student $student,
        string $identifier,
        string $password
    ): bool {
        $identifier = trim($identifier);

        // اسم المستخدم مخزّن بأحرف صغيرة، ورمز المسجد داخل الرقم الذاتي بأحرف
        // كبيرة، فنوحّد الحالة بدل الاتكال على ترتيب مقارنة الجدول.
        $matchesIdentifier = hash_equals(
            strtolower((string) $student->username),
            strtolower($identifier)
        ) || (
            $student->selfnumber !== null
            && hash_equals(
                strtoupper($student->selfnumber),
                strtoupper($identifier)
            )
        );

        // كلمة المرور تُفحص دائماً حتى لو فشل المعرّف، حتى لا يكشف فرق زمن
        // الاستجابة أي الحقلين كان الخطأ.
        $matchesPassword = Hash::check($password, $student->password);

        return $matchesIdentifier && $matchesPassword;
    }

    public function revoke(Request $request, Certificate $certificate): JsonResponse
    {
        $certificate->loadMissing('result.run.cycle');
        abort_unless(
            $this->access->canApproveCycle($request->user(), $certificate->result->run->cycle),
            403
        );
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        return response()->json([
            'message' => 'تم إلغاء الشهادة مع الاحتفاظ بسجلها وبصمتها.',
            'data' => $this->certificates->revoke($certificate, $request->user(), $data['reason']),
        ]);
    }

    /**
     * هذا الرابط مطبوع داخل رمز QR على كل شهادة صادرة، فيخدم حالتين:
     * صفحة نتيجة مقروءة للمتصفح الذي يمسح الرمز، واستجابة JSON لعملاء الـ API.
     */
    public function verify(Request $request, string $token): JsonResponse|Response
    {
        // المتصفحات وحدها ترسل text/html صراحةً؛ عملاء الـ API يرسلون
        // application/json أو */* فتبقى استجابتهم JSON كما هي.
        $wantsPage = str_contains((string) $request->header('Accept'), 'text/html');

        $certificate = preg_match('/^[a-f0-9]{64}$/', $token) === 1
            ? $this->certificates->verify($token)
            : null;

        if (! $certificate) {
            abort_unless($wantsPage, 404, 'رمز التحقق غير صالح أو لا يقابل شهادة سارية.');

            return response()->view('certificates.verify', ['valid' => false, 'certificate' => null], 404);
        }

        $snapshot = $certificate->data_snapshot;
        $data = [
            'serial_number' => $certificate->serial_number,
            'status' => $certificate->status,
            'student_name' => $snapshot['student']['name'],
            'project_name' => $snapshot['project']['name'],
            'cycle_name' => $snapshot['cycle']['name'],
            'final_score' => $snapshot['result']['final_score'],
            'is_excellent' => $snapshot['result']['is_excellent'],
            'rank' => $snapshot['result']['rank'],
            'issued_at' => $certificate->issued_at,
            'file_sha256' => $certificate->file_sha256,
        ];

        return $wantsPage
            ? response()->view('certificates.verify', ['valid' => true, 'certificate' => $data])
            : response()->json(['valid' => true, 'data' => $data]);
    }
}
