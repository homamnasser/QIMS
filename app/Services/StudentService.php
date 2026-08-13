<?php

namespace App\Services;

use App\Enums\RoleFamily;
use App\IService\IStudentService;
use App\Models\Role;
use App\Models\Student;
use App\Traits\FileTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class StudentService implements IStudentService
{
    use FileTrait;

    public function createStudent(array $data): Student
    {
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $this->saveFile($data['image'], 'students/images');
        }

        $student = Student::create($data);

        $role = $roleId
            ? Role::where('role_family', RoleFamily::Student->value)->findOrFail($roleId)
            : Role::firstOrCreate(
                ['name' => 'student', 'guard_name' => 'web'],
                [
                    'role_family' => RoleFamily::Student->value,
                    'is_system' => true,
                    'role_family_reviewed_at' => now(),
                ]
            );
        $student->syncRoles([$role]);

        return $student->load('roles.permissions');
    }

    public function getStudentById(int $id)
    {
        return Student::with([
            'studentCircles.circleDetails.course.mosque',
        ])->find($id);
    }

    public function updateStudent(Student $student, array $data): Student
    {
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($student->image) {
                $this->deleteFile($student->image);
            }
            $data['image'] = $this->saveFile($data['image'], 'students/images');
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $student->update($data);

        if ($roleId) {
            $role = Role::where('role_family', RoleFamily::Student->value)->findOrFail($roleId);
            $student->syncRoles([$role]);
        }

        return $student->refresh()->load('roles.permissions');
    }

    public function deleteStudent(Student $student): bool
    {
        if ($student->image) {
            $this->deleteFile($student->image);
        }

        return $student->delete();
    }

    public function getAllStudents(array $filters = [])
    {
        $query = Student::filter($filters);

        return isset($filters['limit']) ? $query->paginate($filters['limit']) : $query->get();
    }
}
