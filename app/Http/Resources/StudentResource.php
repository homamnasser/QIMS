<?php

namespace App\Http\Resources;

use App\Enums\RoleFamily;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $role = $this->primaryRole();
        $roleFamily = $role?->role_family;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->username,
            'phone_number' => $this->phone_number,
            'birth_date' => $this->birth_date,
            'academic_class' => $this->academic_class,
            'reading_level' => $this->reading_level,
            'father_name' => $this->father_name,
            'parent_social_state' => $this->parent_social_state,
            'father_phone' => $this->father_phone,
            'academic_info' => [
                'class' => $this->academic_class,
                'reading_level' => $this->reading_level,
            ],
            'family_details' => [
                'father_name' => $this->father_name,
                'parent_social_state' => $this->parent_social_state,
                'father_phone' => $this->father_phone,
            ],
            'role_id' => $role?->id,
            'role' => $role?->name,
            'role_family' => $roleFamily instanceof RoleFamily ? $roleFamily->value : $roleFamily,
            'account_type' => 'student',
            'is_super_admin' => false,
            'can_supervise' => false,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->created_at->format('Y-m-d'),
            'image_url' => $this->formatImageUrl($this->image),
            'enrollment' => $this->whenLoaded('studentCircles', function (): array {
                $circles = $this->studentCircles
                    ->map(fn ($assignment) => $assignment->circleDetails)
                    ->filter()
                    ->unique('id')
                    ->values();

                $courses = $circles
                    ->map(fn ($circle) => $circle->course)
                    ->filter()
                    ->unique('id')
                    ->values();

                $mosques = $courses
                    ->map(fn ($course) => $course->mosque)
                    ->filter()
                    ->unique('id')
                    ->values();

                return [
                    'circles' => $circles
                        ->map(fn ($circle): array => [
                            'id' => $circle->id,
                            'name' => $circle->name,
                        ])
                        ->all(),
                    'courses' => $courses
                        ->map(fn ($course): array => [
                            'id' => $course->id,
                            'name' => $course->name,
                        ])
                        ->all(),
                    'mosques' => $mosques
                        ->map(fn ($mosque): array => [
                            'id' => $mosque->id,
                            'name' => $mosque->name,
                        ])
                        ->all(),
                ];
            }),
        ];
    }

    private function formatImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        return asset('storage/'.$cleanPath);
    }
}
