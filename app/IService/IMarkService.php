<?php

namespace App\IService;

use App\Models\StudentMark;

interface IMarkService
{
    /**
     * 🧮 حساب إحصائيات وعلامة حضور الطالب ديناميكياً خلال الدورة
     */
    public function calculateStudentAttendanceMetrics(int $studentId, int $courseId): array;

    /**
     * 🚀 إنشاء سجل علامات جديد للطالب بالاعتماد على الحسابات الديناميكية
     */
    public function createStudentMark(array $data): StudentMark;
}
