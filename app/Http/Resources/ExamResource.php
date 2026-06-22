<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    /**
     * 📊 تحويل كائن العلامة إلى مصفوفة JSON متناسقة ومحمية
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'mark' => $this->mark !== null ? (float)$this->mark : null,


            'student' => [
                'id'        => $this->studentDetails ? $this->studentDetails->id : null,
                'full_name' => $this->studentDetails
                    ? trim($this->studentDetails->first_name . ' ' . $this->studentDetails->last_name)
                    : null,
            ],

            'subject' => [
                'id'       => $this->subjectDetails ? $this->subjectDetails->id : null,
                'name'     => $this->subjectDetails ? $this->subjectDetails->name : null,
                'max_marks' => $this->subjectDetails ? (float)$this->subjectDetails->max_marks : null,
                'min_marks' => $this->subjectDetails ? (float)$this->subjectDetails->min_marks : null,

            ],

            'subject_details' => [
                'id'       => $this->subjectDetails ? $this->subjectDetails->id : null,
                'name'     => $this->subjectDetails ? $this->subjectDetails->name : null,
                'max_marks' => $this->subjectDetails ? (float)$this->subjectDetails->max_marks : null,
                'min_marks' => $this->subjectDetails ? (float)$this->subjectDetails->min_marks : null,
            ],

            'course' => [
                'id'   => $this->courseDetails ? $this->courseDetails->id : null,
                'name' => $this->courseDetails ? $this->courseDetails->name : null,
            ],

            'course_details' => [
                'id'   => $this->courseDetails ? $this->courseDetails->id : null,
                'name' => $this->courseDetails ? $this->courseDetails->name : null,
            ],

            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
