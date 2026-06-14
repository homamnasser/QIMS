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
            // الـ cast إلى float يضمن عودة الرقم بفاصلة (مثال: 95.5) أو صافي بدون أصفار زائدة (مثال: 95) 🎯
            'mark' => $this->mark !== null ? (float)$this->mark : null,

            // استخراج التاريخ والوقت تلقائياً من الـ timestamps الموحدة بالنظام ⏱️
            'date' => $this->created_at ? $this->created_at->format('Y-m-d') : null,

            // تفاصيل الطالب المحمية مع دمج الاسم بستايلك الخاص
            'student' => [
                'id'        => $this->studentDetails ? $this->studentDetails->id : null,
                'full_name' => $this->studentDetails
                    ? trim($this->studentDetails->first_name . ' ' . $this->studentDetails->last_name)
                    : null,
            ],

            // تفاصيل المادة الدراسية
            'subject' => [
                'id'       => $this->subjectDetails ? $this->subjectDetails->id : null,
                'name'     => $this->subjectDetails ? $this->subjectDetails->name : null,
                'max_mark' => $this->subjectDetails ? (float)$this->subjectDetails->max_mark : null,
            ],

            // تفاصيل الكورس التابع له الامتحان
            'course' => [
                'id'   => $this->courseDetails ? $this->courseDetails->id : null,
                'name' => $this->courseDetails ? $this->courseDetails->name : null,
            ],
        ];
    }
}
