<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\EvaluationResult;
use App\Models\EvaluationRun;
use App\Models\Student;
use App\Services\Evaluation\CertificateService;
use App\Services\Evaluation\EvaluationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $issued = [];
        foreach ($run->results->where('status', 'published') as $result) {
            $issued[] = $this->certificates->issue($result, $request->user());
        }

        return response()->json([
            'message' => 'تم إصدار شهادات النتائج المنشورة.',
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
        abort_unless($certificate->status === 'issued', 410);
        $this->certificates->assertFileIntegrity($certificate);

        return Storage::disk($certificate->file_disk)->download(
            $certificate->file_path,
            $certificate->serial_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function studentDownload(Request $request, int $certificateId): StreamedResponse
    {
        /** @var Student $student */
        $student = $request->user();
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

    public function verify(string $token): JsonResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 404);
        $certificate = $this->certificates->verify($token);
        abort_unless($certificate, 404);
        $snapshot = $certificate->data_snapshot;

        return response()->json([
            'valid' => true,
            'data' => [
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
            ],
        ]);
    }
}
