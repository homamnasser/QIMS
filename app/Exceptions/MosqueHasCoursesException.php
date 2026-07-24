<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class MosqueHasCoursesException extends RuntimeException
{
    public function __construct(
        public readonly int $coursesCount = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'لا يمكن حذف المسجد لأنه مرتبط بكورس واحد أو أكثر.',
            0,
            $previous,
        );
    }
}
