<?php

namespace App\IService;

use Illuminate\Support\Collection;

interface INoteService
{
    public function createNote(array $data, int $userId);
    public function getNotesByStudentId(int $studentId);
    public function deleteNote(int $noteId, int $userId, bool $mayDeleteAny = false): bool;
    public function getNotesByTeacher(int $teacherId, array $filters = []): Collection;
}
