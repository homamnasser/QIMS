<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('memorizations')
            ->where(function ($query) {
                $query->whereNull('course_id')
                    ->orWhereNull('circle_id')
                    ->orWhereNull('recorded_at');
            })
            ->orderBy('id')
            ->eachById(function ($record): void {
                $update = [];
                $recordedAt = $record->recorded_at ?? $record->created_at;
                if ($record->recorded_at === null && $recordedAt !== null) {
                    $update['recorded_at'] = $recordedAt;
                }

                $circle = $this->resolveCircle($record);
                if ($circle !== null) {
                    if ($record->circle_id === null) {
                        $update['circle_id'] = $circle->id;
                    }
                    if ($record->course_id === null) {
                        $update['course_id'] = $circle->course_id;
                    }

                    $courseDateId = DB::table('course_dates')
                        ->where('course_id', $circle->course_id)
                        ->whereDate('session_date', substr((string) $recordedAt, 0, 10))
                        ->when($record->course_date_id !== null, fn ($query) => $query
                            ->where('id', $record->course_date_id))
                        ->value('id');
                    if ($record->course_date_id === null && $courseDateId !== null) {
                        $update['course_date_id'] = $courseDateId;
                    }
                }

                if ($update !== []) {
                    DB::table('memorizations')
                        ->where('id', $record->id)
                        ->update($update);
                }
            });
    }

    public function down(): void
    {
        // The inferred links are deliberately retained: rollback must not erase
        // operational context that may have been reviewed after this migration.
    }

    private function resolveCircle(object $record): ?object
    {
        if ($record->circle_id !== null) {
            return DB::table('circles')
                ->where('id', $record->circle_id)
                ->first(['id', 'course_id']);
        }

        $matches = DB::table('student_circles as enrollment')
            ->join('circles as circle', 'circle.id', '=', 'enrollment.circle')
            ->where('enrollment.student', $record->student)
            ->where('circle.teacher_id', $record->giver)
            ->when($record->course_id !== null, fn ($query) => $query
                ->where('circle.course_id', $record->course_id))
            ->distinct()
            ->get(['circle.id', 'circle.course_id']);

        return $matches->count() === 1 ? $matches->first() : null;
    }
};
