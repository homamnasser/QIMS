<?php
namespace App\IService;

use App\Models\Course;

interface ICourseService
{
    /**
     * إنشاء كورس جديد
     * @param array $data
     * @return Course
     */
    public function createCourse(array $data): Course;
}
