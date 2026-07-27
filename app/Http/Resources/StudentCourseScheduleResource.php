<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCourseScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'course' => new StudentLearningCourseResource($this->resource),
            'schedule' => $this->whenLoaded('courseDates', fn () => $this->courseDates
                ->map(fn ($courseDate): array => [
                    'id' => $courseDate->id,
                    'session_date' => $courseDate->session_date,
                    'day_name' => Carbon::parse($courseDate->session_date)->format('l'),
                    'lessons' => LessonResource::collection($courseDate->lessons),
                ])
                ->all()),
        ];
    }
}
