<?php

namespace App\IService;

use App\Models\Student;

interface IStudentService
{
    public function createStudent(array $data): Student;
    public function getStudentById(int $id);
    public function updateStudent(Student $student, array $data): Student;
    public function deleteStudent(Student $student): bool;
    public function getAllStudents(array $filters = []);
}
