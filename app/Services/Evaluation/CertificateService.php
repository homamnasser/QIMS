<?php

namespace App\Services\Evaluation;

use App\Models\Certificate;
use App\Models\EvaluationResult;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

class CertificateService
{
    public function __construct(private readonly EvaluationAuditService $audit) {}

    public function issue(EvaluationResult $result, User $actor): Certificate
    {
        if ($result->status !== 'published') {
            throw ValidationException::withMessages([
                'result' => ['لا تصدر الشهادة إلا لنتيجة منشورة.'],
            ]);
        }

        $existing = $result->certificates()->where('status', 'issued')->latest('version')->first();
        if ($existing) {
            return $existing;
        }

        $result->loadMissing([
            'run.cycle.project:id,name',
            'candidate.student:id,first_name,last_name,selfnumber,academic_class',
            'candidate.enrollments',
            'criteria',
        ]);

        $reservation = DB::transaction(function () use ($result, $actor) {
            $lockedResult = EvaluationResult::query()->lockForUpdate()->findOrFail($result->id);
            $existing = $lockedResult->certificates()
                ->where('status', 'issued')
                ->latest('version')
                ->first();
            if ($existing) {
                return ['certificate' => $existing, 'is_new' => false];
            }

            $generating = $lockedResult->certificates()
                ->where('status', 'generating')
                ->latest('version')
                ->first();
            if ($generating && $generating->created_at->greaterThan(now()->subMinutes(10))) {
                throw ValidationException::withMessages([
                    'certificate' => ['إصدار الشهادة قيد التنفيذ حالياً.'],
                ]);
            }
            if ($generating) {
                $generating->update(['status' => 'failed']);
            }

            $version = ((int) $lockedResult->certificates()->max('version')) + 1;
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $serial = sprintf(
                'QIMS-%s-%05d-%05d-V%d',
                now()->format('Y'),
                $result->run->cycle->id,
                $result->id,
                $version
            );
            $verificationUrl = rtrim(config('evaluation.certificate.public_verification_url'), '/')
                .'/'.$token;
            $snapshot = $this->snapshot($result, $serial, $verificationUrl);
            $disk = config('evaluation.certificate.disk');
            $filePath = 'certificates/final-results/'.$serial.'.pdf';
            $certificate = Certificate::create([
                'evaluation_result_id' => $result->id,
                'certificate_type' => 'final_result',
                'serial_number' => $serial,
                'verification_token_hash' => $tokenHash,
                'file_disk' => $disk,
                'file_path' => $filePath,
                'file_sha256' => str_repeat('0', 64),
                'data_snapshot' => $snapshot,
                'status' => 'generating',
                'version' => $version,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);

            return [
                'certificate' => $certificate,
                'is_new' => true,
                'snapshot' => $snapshot,
                'verification_url' => $verificationUrl,
            ];
        });

        if (! $reservation['is_new']) {
            return $reservation['certificate'];
        }

        /** @var Certificate $certificate */
        $certificate = $reservation['certificate'];
        $disk = Storage::disk($certificate->file_disk);

        try {
            $qrSvg = $this->qrSvg($reservation['verification_url']);
            $pdf = $this->renderPdf($reservation['snapshot'], $qrSvg);
            $disk->put($certificate->file_path, $pdf);
            $certificate->update([
                'file_sha256' => hash('sha256', $pdf),
                'status' => 'issued',
            ]);
            $this->audit->record('evaluation.certificate_issued', $certificate, $actor);

            return $certificate;
        } catch (\Throwable $exception) {
            if ($disk->exists($certificate->file_path)) {
                $disk->delete($certificate->file_path);
            }
            $certificate->update(['status' => 'failed']);

            throw $exception;
        }
    }

    public function verify(string $token): ?Certificate
    {
        return Certificate::query()
            ->where('verification_token_hash', hash('sha256', $token))
            ->where('status', 'issued')
            ->first();
    }

    public function assertFileIntegrity(Certificate $certificate): void
    {
        $disk = Storage::disk($certificate->file_disk);
        if (! $disk->exists($certificate->file_path)
            || hash('sha256', $disk->get($certificate->file_path)) !== $certificate->file_sha256) {
            logger()->error('فشل التحقق من سلامة ملف الشهادة.', [
                'error_code' => 'CERTIFICATE_FILE_CORRUPT',
                'certificate_id' => $certificate->id,
                'serial_number' => $certificate->serial_number,
                'file_disk' => $certificate->file_disk,
                'file_path' => $certificate->file_path,
            ]);

            abort(409, 'ملف الشهادة مفقود أو لا يطابق بصمته المحفوظة؛ يجب إلغاء الشهادة وإعادة إصدارها.');
        }
    }

    public function renderPreview(array $snapshot): string
    {
        if (! isset($snapshot['verification_url'])) {
            throw new RuntimeException('يجب توفير رابط تحقق داخل بيانات معاينة الشهادة.');
        }

        return $this->renderPdf(
            $snapshot,
            $this->qrSvg($snapshot['verification_url'])
        );
    }

    public function revoke(Certificate $certificate, User $actor, string $reason): Certificate
    {
        if ($certificate->status !== 'issued') {
            throw ValidationException::withMessages([
                'certificate' => ['لا يمكن إلغاء شهادة غير سارية.'],
            ]);
        }

        $before = $certificate->toArray();
        $certificate->update([
            'status' => 'revoked',
            'revoked_by' => $actor->id,
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
        $this->audit->record(
            'evaluation.certificate_revoked',
            $certificate,
            $actor,
            $before,
            $certificate->toArray()
        );

        return $certificate;
    }

    private function snapshot(EvaluationResult $result, string $serial, string $verificationUrl): array
    {
        $cycle = $result->run->cycle;
        $student = $result->candidate->student;

        return [
            'serial_number' => $serial,
            'issuer_name' => config('evaluation.certificate.issuer_name'),
            'student' => [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'selfnumber' => $student->selfnumber,
                'academic_class' => $student->academic_class,
            ],
            'project' => [
                'id' => $cycle->project->id,
                'name' => $cycle->project->name,
            ],
            'cycle' => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'season' => $cycle->season,
                'start_date' => $cycle->start_date->toDateString(),
                'end_date' => $cycle->end_date->toDateString(),
            ],
            'result' => [
                'base_score' => (float) $result->base_score,
                'base_maximum' => (float) $result->base_maximum,
                'bonus_score' => (float) $result->bonus_score,
                'final_score' => (float) $result->final_score,
                'final_percentage' => (float) $result->final_percentage,
                'is_excellent' => $result->is_excellent,
                'rank' => $result->rank,
            ],
            'criteria' => $result->criteria
                ->where('criterion_key', '!=', 'sabr_bonus')
                ->map(fn ($criterion) => [
                    'key' => $criterion->criterion_key,
                    'name' => $criterion->criterion_name,
                    'is_applicable' => $criterion->is_applicable,
                    'score' => (float) $criterion->score,
                    'maximum_score' => (float) $criterion->maximum_score,
                ])->values()->all(),
            'issued_at' => now()->toIso8601String(),
            'verification_url' => $verificationUrl,
        ];
    }

    private function qrSvg(string $verificationUrl): string
    {
        return (new Builder(
            writer: new SvgWriter,
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
            ],
            data: $verificationUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 8,
            foregroundColor: new Color(24, 73, 66),
        ))->build()->getString();
    }

    private function renderPdf(array $snapshot, string $qrSvg): string
    {
        $chrome = config('evaluation.certificate.chrome_binary');
        if (! is_file($chrome) || ! is_executable($chrome)) {
            logger()->error('محرك توليد الشهادات غير متاح.', [
                'error_code' => 'CERTIFICATE_ENGINE_UNAVAILABLE',
                'configured_path' => $chrome,
            ]);

            abort(503, 'خدمة توليد الشهادات غير متاحة حاليًا؛ يرجى إبلاغ الفريق التقني ثم إعادة المحاولة.');
        }

        $tempDirectory = storage_path('app/private/certificate-temp');
        File::ensureDirectoryExists($tempDirectory);
        $nonce = bin2hex(random_bytes(12));
        $htmlPath = $tempDirectory.'/'.$nonce.'.html';
        $pdfPath = $tempDirectory.'/'.$nonce.'.pdf';

        try {
            File::put($htmlPath, view('certificates.final-result', [
                'certificate' => $snapshot,
                'qrSvg' => $qrSvg,
            ])->render());

            $process = new Process([
                $chrome,
                '--headless=new',
                '--no-sandbox',
                '--disable-gpu',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf='.$pdfPath,
                'file://'.$htmlPath,
            ]);
            $process->setTimeout(45);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($pdfPath)) {
                // مخرجات Chrome فنية وطويلة؛ تُحفظ في السجل ولا تُرسل للواجهة.
                logger()->error('فشل توليد ملف الشهادة عبر Chrome.', [
                    'error_code' => 'CERTIFICATE_RENDER_FAILED',
                    'exit_code' => $process->getExitCode(),
                    'stderr' => trim($process->getErrorOutput()),
                ]);

                abort(500, 'تعذر توليد ملف الشهادة. تم تسجيل التفاصيل الفنية للفريق التقني.');
            }

            return File::get($pdfPath);
        } finally {
            File::delete([$htmlPath, $pdfPath]);
        }
    }
}
