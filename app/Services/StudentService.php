<?php

namespace App\Services;

use App\Models\Student;
use Spatie\Permission\Models\Role;
use App\IService\IStudentService;

class StudentService implements IStudentService
{
    public function createStudent(array $data): Student
    {
        $student = Student::create($data);

        $role = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->assignRole($role);

        return $student;
    }
}
