<?php

namespace Tests\Unit;

use App\Services\Evaluation\CertificateService;
use App\Services\Evaluation\EvaluationAuditService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CertificateFileUrlTest extends TestCase
{
    #[DataProvider('localPaths')]
    public function test_it_builds_portable_local_file_urls(string $path, string $expected): void
    {
        $service = new class(new EvaluationAuditService) extends CertificateService
        {
            public function fileUrl(string $path): string
            {
                return $this->localFileUrl($path);
            }
        };

        $this->assertSame($expected, $service->fileUrl($path));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function localPaths(): array
    {
        return [
            'Linux path' => [
                '/home/qims/certificate temp/certificate.html',
                'file:///home/qims/certificate%20temp/certificate.html',
            ],
            'Windows path' => [
                'C:\\Users\\QIMS User\\certificate-temp\\certificate.html',
                'file:///C:/Users/QIMS%20User/certificate-temp/certificate.html',
            ],
        ];
    }
}
