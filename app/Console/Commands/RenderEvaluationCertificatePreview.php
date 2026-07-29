<?php

namespace App\Console\Commands;

use App\Services\Evaluation\CertificateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RenderEvaluationCertificatePreview extends Command
{
    protected $signature = 'evaluation:certificate-preview';

    protected $description = 'Render a visual QA sample of the Arabic final evaluation certificate';

    public function handle(CertificateService $certificates): int
    {
        $outputDirectory = base_path('output/pdf');
        $outputPath = $outputDirectory.'/evaluation-certificate-preview.pdf';
        File::ensureDirectoryExists($outputDirectory);

        $snapshot = [
            'serial_number' => 'QIMS-2026-00001-00001-V1',
            'issuer_name' => config('evaluation.certificate.issuer_name'),
            'student' => [
                'id' => 1,
                'name' => 'أحمد محمد الأنصاري',
                'selfnumber' => 'A-000125',
                'academic_class' => 'الصف الثامن',
            ],
            'project' => [
                'id' => 1,
                'name' => 'برنامج ناشئة الهدى',
            ],
            'cycle' => [
                'id' => 1,
                'name' => 'التقييم الشتوي النهائي 2026',
                'season' => 'winter',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
            ],
            'result' => [
                'base_score' => 431.5,
                'base_maximum' => 455,
                'bonus_score' => 25,
                'final_score' => 456.5,
                'final_percentage' => 100.33,
                'is_excellent' => true,
                'rank' => 2,
            ],
            'criteria' => [
                ['key' => 'attendance', 'name' => 'الحضور', 'is_applicable' => true, 'score' => 124, 'maximum_score' => 130],
                ['key' => 'reading', 'name' => 'التحسن في القراءة', 'is_applicable' => true, 'score' => 25, 'maximum_score' => 25],
                ['key' => 'quran', 'name' => 'التسميع والمراجعة', 'is_applicable' => true, 'score' => 92.5, 'maximum_score' => 100],
                ['key' => 'theoretical_exams', 'name' => 'الامتحانات النظرية', 'is_applicable' => true, 'score' => 88, 'maximum_score' => 100],
                ['key' => 'teacher_evaluation', 'name' => 'تقييم المدرس', 'is_applicable' => true, 'score' => 48, 'maximum_score' => 50],
                ['key' => 'administration_evaluation', 'name' => 'تقييم الإدارة', 'is_applicable' => true, 'score' => 50, 'maximum_score' => 50],
            ],
            'issued_at' => now()->toIso8601String(),
            'verification_url' => rtrim(config('evaluation.certificate.public_verification_url'), '/')
                .'/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        ];

        File::put($outputPath, $certificates->renderPreview($snapshot));
        $this->info($outputPath);

        return self::SUCCESS;
    }
}
