<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

class SurveyStudentFieldRegistry
{
    /**
     * This allow-list is the privacy boundary for data that can be exposed by
     * a public survey. Adding a field requires an explicit entry here.
     */
    public function catalog(): array
    {
        return [
            'basic.full_name' => $this->field('الاسم الكامل', 'basic', 'text'),
            'basic.first_name' => $this->field('الاسم الأول', 'basic', 'text'),
            'basic.last_name' => $this->field('الكنية', 'basic', 'text'),
            'basic.birth_date' => $this->field('تاريخ الميلاد', 'basic', 'date'),
            'basic.academic_class' => $this->field('الصف الدراسي', 'basic', 'text'),
            'basic.reading_level' => $this->field('مستوى القراءة', 'basic', 'text'),
            'basic.phone_number' => $this->field('رقم هاتف الطالب', 'basic', 'text', true),
            'family.father_name' => $this->field('اسم الأب', 'family', 'text', true),
            'family.parent_social_state' => $this->field('الحالة الاجتماعية لولي الأمر', 'family', 'text', true),
            'family.father_phone' => $this->field('رقم هاتف ولي الأمر', 'family', 'text', true),
            'enrollment.mosque' => $this->field('المسجد', 'enrollment', 'text'),
            'enrollment.circles' => $this->field('الحلقات الدراسية', 'enrollment', 'list'),
            'enrollment.courses' => $this->field('الكورسات', 'enrollment', 'list'),
            'enrollment.projects' => $this->field('المشاريع', 'enrollment', 'list'),
        ];
    }

    public function availableForUser(User $user): array
    {
        return collect($this->catalog())
            ->reject(fn (array $field): bool => $field['sensitive'] && ! $user->can('عرض تفاصيل الطالب'))
            ->map(fn (array $field, string $key): array => ['key' => $key, ...$field])
            ->values()
            ->all();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->catalog());
    }

    public function definition(string $key): ?array
    {
        return $this->catalog()[$key] ?? null;
    }

    public function resolve(Student $student): array
    {
        $student->loadMissing('studentCircles.circleDetails.course.mosque', 'studentCircles.circleDetails.course.project');

        $circles = $student->studentCircles
            ->map(fn ($assignment) => $assignment->circleDetails)
            ->filter()
            ->unique('id')
            ->values();
        $courses = $circles->map(fn ($circle) => $circle->course)->filter()->unique('id')->values();
        $projects = $courses->map(fn ($course) => $course->project)->filter()->unique('id')->values();
        $mosque = $student->mosque ?: $courses->first()?->mosque;

        return [
            'basic.full_name' => trim($student->first_name.' '.$student->last_name),
            'basic.first_name' => $student->first_name,
            'basic.last_name' => $student->last_name,
            'basic.birth_date' => $student->birth_date?->format('Y-m-d'),
            'basic.academic_class' => $student->academic_class,
            'basic.reading_level' => $student->reading_level,
            'basic.phone_number' => $student->phone_number,
            'family.father_name' => $student->father_name,
            'family.parent_social_state' => $student->parent_social_state,
            'family.father_phone' => $student->father_phone,
            'enrollment.mosque' => $mosque?->name,
            'enrollment.circles' => $this->names($circles),
            'enrollment.courses' => $this->names($courses),
            'enrollment.projects' => $this->names($projects),
        ];
    }

    private function field(string $label, string $group, string $type, bool $sensitive = false): array
    {
        return compact('label', 'group', 'type', 'sensitive');
    }

    private function names(Collection $models): array
    {
        return $models->pluck('name')->filter()->values()->all();
    }
}
