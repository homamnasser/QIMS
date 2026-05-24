<?php

namespace App\IService;

interface INoteService
{
    public function createNote(array $data, int $userId);
    public function getNotesByStudentId(int $studentId);
    public function deleteNote(int $noteId, int $userId): bool;
    public function getNotesByTeacherId(int $userId);
}
