<?php

namespace App\IService;

use App\Models\CourseDate;

interface ICourseDateService
{
    public function generateCourseDates(int $courseId, array $data);
    public function getDatesByCourse(int $courseId);
    public function deleteDate(CourseDate $courseDate);
    public function getCourseDateById(int $id);
    public function addManualDate(array $data);
    
}
