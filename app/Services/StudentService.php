<?php

namespace App\Services;

use App\Models\Student;
use Spatie\Permission\Models\Role;
use App\IService\IStudentService;
use App\Traits\FileTrait;
use Illuminate\Support\Facades\Hash;

class StudentService implements IStudentService
{
    use FileTrait;

    public function createStudent(array $data): Student
    {
        if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
            $data['image'] = $this->saveFile($data['image'], 'students/images');
        }

        $student = Student::create($data);

        $role = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->assignRole($role);

        return $student;
    }

    public function getStudentById(int $id)
    {
        return Student::find($id);
    }

    public function updateStudent(Student $student, array $data): Student
    {
        if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
            if ($student->image) {
                $this->deleteFile($student->image);
            }
            $data['image'] = $this->saveFile($data['image'], 'students/images');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $student->update($data);

        return $student->refresh();
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
        return Student::filter($filters)->get();
    }
}
