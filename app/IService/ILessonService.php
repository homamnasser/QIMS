<?php
namespace App\IService;
use App\Models\Lesson;
interface ILessonService
{
    public function getAllLessons(array $filters);
    public function getLessonById(int $id): ?Lesson;
    public function createLesson(array $data): Lesson;
    public function updateLesson(Lesson $lesson, array $data): Lesson;
    public function deleteLesson(Lesson $lesson): bool;
}
