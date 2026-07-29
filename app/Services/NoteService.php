<?php

namespace App\Services;

use App\IService\INoteService;
use App\Models\Note;
use Illuminate\Support\Collection;
class NoteService implements INoteService
{
    /**
     * إنشاء ملاحظة جديدة للطالب بأمان
     */
    public function createNote(array $data, int $userId)
    {
        return Note::create([
            'student_id'  => $data['student_id'],
            'user_id'     => $userId, // حقن الأيدي من الـ Auth
            'title'       => $data['title'],
            'description' => $data['description'],
        ]);
    }

    /**
     * جلب جميع ملاحظات طالب معين مع العلاقات
     */
    public function getNotesByStudentId(int $studentId)
    {
        return Note::where('student_id', $studentId)
            ->with(['student', 'author'])
            ->get();
    }

    /**
     * حذف ملاحظة (أمنياً: الأستاذ الذي كتب الملاحظة هو فقط من يستطيع حذفها)
     */
    public function deleteNote(int $noteId, int $userId, bool $mayDeleteAny = false): bool
    {
       return (bool) Note::query()
           ->whereKey($noteId)
           ->when(! $mayDeleteAny, fn ($query) => $query->where('user_id', $userId))
           ->delete();
    }

    public function getNotesByTeacher(int $teacherId, array $filters = []): Collection
{
    return Note::where('user_id', $teacherId)
        ->filter($filters)
        ->latest()
        ->get();
}
}
