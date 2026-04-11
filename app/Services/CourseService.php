<?php
namespace App\Services;

use App\Models\Course;
use Exception;
use App\IService\ICourseService;
class CourseService implements ICourseService
{
    public function createCourse(array $data): Course
    {
        
        return Course::create($data);
    }
}
