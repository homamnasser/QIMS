<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MosqueHasScopedRecordsException extends RuntimeException
{
    public function __construct(
        public readonly int $staffCount = 0,
        public readonly int $studentsCount = 0,
        public readonly int $surveysCount = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'لا يمكن حذف المسجد لأنه مستخدم كنطاق عمل أو مرتبط ببيانات تابعة له.',
            0,
            $previous,
        );
    }
}
