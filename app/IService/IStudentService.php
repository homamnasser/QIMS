<?php

namespace App\IService;

use App\Models\Student;

interface IStudentService
{
    public function createStudent(array $data): Student;




}
