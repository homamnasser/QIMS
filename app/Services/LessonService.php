<?php

namespace App\Services;

use App\IService\ILessonService;
use App\Models\Lesson;

class LessonService implements ILessonService
{
   public function getAllLessons(array $filters)
{
    return Lesson::with('subject')
        ->filter($filters)
        ->latest()
        ->get();
}

    public function getLessonById(int $id): ?Lesson
    {
        return Lesson::find($id);
    }

    public function createLesson(array $data): Lesson
    {
        return Lesson::create($data);
    }

    public function updateLesson(Lesson $lesson, array $data): Lesson
    {
        $lesson->update($data);
        return $lesson->fresh();
    }

    public function deleteLesson(Lesson $lesson): bool
    {
        return $lesson->delete();
    }
}
