<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseCurriculumController;
use App\Http\Controllers\CourseDateController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MemorizationController;
use App\Http\Controllers\MosqueController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\ReportApiController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SabrController;
use App\Http\Controllers\StudentCircleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentCourseAbsenceController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\WarningController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/loginUser', [AuthController::class, 'loginUser']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::prefix('public/surveys')->group(function (): void {
    Route::get('/{publicToken}', [PublicSurveyController::class, 'show']);
    Route::post('/{publicToken}/identify', [PublicSurveyController::class, 'identify']);
    Route::post('/{publicToken}/responses', [PublicSurveyController::class, 'submit']);
});

Route::middleware(['api', 'auth:sanctum'])->prefix('surveys')->group(function (): void {
    Route::get('/student-fields', [SurveyController::class, 'studentFields'])
        ->middleware('permission:إنشاء استبيان|تعديل استبيان');
    Route::get('/files/{accessToken}', [SurveyController::class, 'downloadFile'])
        ->middleware('permission:عرض وتصدير ردود الاستبيان');
    Route::get('/', [SurveyController::class, 'index'])
        ->middleware('permission:عرض كافة الاستبيانات');
    Route::post('/', [SurveyController::class, 'store'])
        ->middleware('permission:إنشاء استبيان');
    Route::get('/{survey}', [SurveyController::class, 'show'])
        ->middleware('permission:عرض تفاصيل الاستبيان');
    Route::put('/{survey}', [SurveyController::class, 'update'])
        ->middleware('permission:تعديل استبيان');
    Route::delete('/{survey}', [SurveyController::class, 'destroy'])
        ->middleware('permission:حذف استبيان');
    Route::put('/{survey}/definition', [SurveyController::class, 'saveDefinition'])
        ->middleware('permission:تعديل استبيان');
    Route::post('/{survey}/publication', [SurveyController::class, 'publication'])
        ->middleware('permission:نشر وإلغاء نشر الاستبيان');
    Route::get('/{survey}/responses', [SurveyController::class, 'responses'])
        ->middleware('permission:عرض وتصدير ردود الاستبيان');
    Route::get('/{survey}/responses/{response}', [SurveyController::class, 'response'])
        ->middleware('permission:عرض وتصدير ردود الاستبيان');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
], function ($router) {
    Route::post('/createStaffMember', [AuthController::class, 'createStaffMember'])->middleware('permission:إنشاء موظف');
    Route::post('/updateStaffMember/{id}', [AuthController::class, 'updateStaffMember'])->middleware('permission:تعديل موظف');
    Route::delete('/deleteStaffMember/{id}', [AuthController::class, 'deleteStaffMember'])->middleware('permission:حذف موظف');
    Route::get('/getStaffById/{id}', [AuthController::class, 'getStaffById'])->middleware('permission:عرض تفاصيل الموظف');
    Route::get('/getAllStaff', [AuthController::class, 'getAllStaff'])->middleware('permission:عرض كافة الموظفين');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'role',
], function ($router) {
    Route::post('/createRole', [RoleController::class, 'createRole'])->middleware('permission:إنشاء دور');
    Route::get('/getAllRoles', [RoleController::class, 'getAllRoles'])->middleware('permission:عرض كافة الأدوار');
    Route::get('/getRole/{id}', [RoleController::class, 'getRole'])->middleware('permission:عرض تفاصيل الدور');
    Route::post('/updateRole/{id}', [RoleController::class, 'updateRole'])->middleware('permission:تعديل الدور');
    Route::delete('/deleteRole/{id}', [RoleController::class, 'deleteRole'])->middleware('permission:حذف الدور');
    Route::get('/getAllPermissions', [RoleController::class, 'getAllPermissions'])->middleware('permission:عرض كافة الصلاحيات');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'project',
], function ($router) {
    Route::post('/createProject', [ProjectController::class, 'createProject'])->middleware('permission:إنشاء مشروع');
    Route::get('/getAllProjects', [ProjectController::class, 'getAllProjects'])->middleware('permission:عرض كافة المشاريع');
    Route::get('/getAudiences', [ProjectController::class, 'getAudiences'])->middleware('permission:عرض كافة المشاريع');
    Route::get('/getProject/{id}', [ProjectController::class, 'getProject'])->middleware('permission:عرض تفاصيل المشروع');
    Route::post('/updateProject/{id}', [ProjectController::class, 'updateProject'])->middleware('permission:تعديل المشروع');
    Route::delete('/deleteProject/{id}', [ProjectController::class, 'deleteProject'])->middleware('permission:حذف المشروع');
    Route::post('/editProjectStatus/{id}', [ProjectController::class, 'editProjectStatus'])->middleware('permission:تعديل حالة المشروع');
    Route::get('/getMyProjects', [ProjectController::class, 'getMyProjects'])
        ->middleware('permission:الإشراف على المشاريع والكورسات');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'mosque',
], function ($router) {
    Route::post('/createMosque', [MosqueController::class, 'createMosque'])->middleware('permission:إنشاء مسجد');
    Route::get('/getAllMosques', [MosqueController::class, 'getAllMosques'])->middleware('permission:عرض كافة المساجد');
    Route::get('/getMosque/{id}', [MosqueController::class, 'getMosque'])->middleware('permission:عرض تفاصيل المسجد');
    Route::post('/updateMosque/{id}', [MosqueController::class, 'updateMosque'])->middleware('permission:تعديل مسجد');
    Route::delete('/deleteMosque/{id}', [MosqueController::class, 'deleteMosque'])->middleware('permission:حذف مسجد');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'course',
], function ($router) {
    Route::post('/createCourse', [CourseController::class, 'createCourse'])->middleware('permission:إنشاء كورس');
    Route::get('/getAllCourses', [CourseController::class, 'getAllCourses'])->middleware('permission:عرض كافة الكورسات');
    Route::get('/getCourse/{id}', [CourseController::class, 'getCourse'])->middleware('permission:عرض تفاصيل الكورس');
    Route::post('/updateCourse/{id}', [CourseController::class, 'updateCourse'])->middleware('permission:تعديل الكورس');
    Route::delete('/deleteCourse/{id}', [CourseController::class, 'deleteCourse'])->middleware('permission:حذف كورس');
    Route::post('/editCourseStatus/{id}', [CourseController::class, 'editCourseStatus'])->middleware('permission:تعديل حالة الكورس');
    Route::get('/getMyCourses', [CourseController::class, 'getMyCourses'])
        ->middleware('permission:الإشراف على المشاريع والكورسات');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'subject',
], function ($router) {
    Route::post('/createSubject', [SubjectController::class, 'createSubject'])->middleware('permission:إنشاء مادة');
    Route::get('/getAllSubjects', [SubjectController::class, 'getAllSubjects'])->middleware('permission:عرض كافة المواد');
    Route::get('/getSubject/{id}', [SubjectController::class, 'getSubject'])->middleware('permission:عرض تفاصيل المادة');
    Route::post('/updateSubject/{id}', [SubjectController::class, 'updateSubject'])->middleware('permission:تعديل المادة');
    Route::delete('/deleteSubject/{id}', [SubjectController::class, 'deleteSubject'])->middleware('permission:حذف مادة');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'lesson',
], function ($router) {
    Route::post('/createLesson', [LessonController::class, 'createLesson'])->middleware('permission:إنشاء درس');
    Route::get('/getAllLessons', [LessonController::class, 'getAllLessons'])->middleware('permission:عرض كافة الدروس');
    Route::get('/getLesson/{id}', [LessonController::class, 'getLesson'])->middleware('permission:عرض تفاصيل الدرس');
    Route::post('/updateLesson/{id}', [LessonController::class, 'updateLesson'])->middleware('permission:تعديل الدرس');
    Route::delete('/deleteLesson/{id}', [LessonController::class, 'deleteLesson'])->middleware('permission:حذف درس');
});
Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'courseDate',
], function ($router) {
    Route::post('/createCourseDate', [CourseDateController::class, 'createCourseDate'])->middleware('permission:إنشاء تاريخ الكورس');
    Route::get('/getDatesByCourse/{courseId}', [CourseDateController::class, 'getDateByCourse'])->middleware('permission:عرض تواريخ الكورس');
    Route::delete('/deleteDate/{id}', [CourseDateController::class, 'deleteDate'])->middleware('permission:حذف تاريخ');
    Route::post('/createDateManual', [CourseDateController::class, 'createDateManual'])->middleware('permission:إضافة تاريخ يدوياً');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'dateLesson',
], function ($router) {
    Route::post('/assignLessonsToDate', [CourseCurriculumController::class, 'assignLessonsToDate'])->middleware('permission:إسناد الدروس للتاريخ');
    Route::post('/updateAssignLessonsToDate/{courseDate}', [CourseCurriculumController::class, 'updateAssignLessonsToDate'])->middleware('permission:تعديل إسناد دروس التاريخ');
    Route::delete('/detachAllLessons/{courseDateId}', [CourseCurriculumController::class, 'deleteLessonsFromDate'])->middleware('permission:حذف دروس التاريخ');
    Route::get('/getLessonsByDate/{id}', [CourseCurriculumController::class, 'getLessonsByDate'])->middleware('permission:عرض دروس التاريخ');
    Route::get('/getCurriculumByCourse/{courseId}', [CourseCurriculumController::class, 'getCurriculumByCourse'])->middleware('permission:عرض المنهج الدراسي للكورس');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'circle',
], function ($router) {
    Route::post('/createCircle', [CircleController::class, 'createCircle'])->middleware('permission:إنشاء حلقة');
    Route::get('/getMyCircleCurriculum', [CircleController::class, 'getMyCircleCurriculum'])->middleware('permission:عرض منهج حلقتي');
    Route::get('/getCircle/{id}', [CircleController::class, 'getCircle'])->middleware('permission:عرض تفاصيل الحلقة');
    Route::get('/getAllCircles', [CircleController::class, 'getAllCircles'])->middleware('permission:عرض كافة الحلقات');
    Route::delete('/deleteCircle/{id}', [CircleController::class, 'deleteCircle'])->middleware('permission:حذف الحلقة');
    Route::post('/updateCircle/{id}', [CircleController::class, 'updateCircle'])->middleware('permission:تعديل الحلقة');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'student',
], function ($router) {
    Route::post('/createStudent', [StudentController::class, 'createStudent'])->middleware('permission:إنشاء طالب');
    Route::get('/getStudentById/{id}', [StudentController::class, 'getStudentById'])->middleware('permission:عرض تفاصيل الطالب');
    Route::get('/getAllStudents', [StudentController::class, 'getAllStudents'])->middleware('permission:عرض كافة الطلاب');
    Route::post('/updateStudent/{id}', [StudentController::class, 'updateStudent'])->middleware('permission:تعديل الطالب');
    Route::delete('/deleteStudent/{id}', [StudentController::class, 'deleteStudent'])->middleware('permission:حذف الطالب');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'studentCircle',
], function ($router) {
    Route::post('/addStudentsToCircle', [StudentCircleController::class, 'addStudents'])->middleware('permission:إضافة طلاب للحلقة');
    Route::post('/removeStudentFromCircle', [StudentCircleController::class, 'removeStudent'])->middleware('permission:إلغاء التحاق طالب بالحلقة');
    Route::get('/getStudentsByCircle/{circleId}', [StudentCircleController::class, 'getStudents'])->middleware('permission:عرض طلاب الحلقة');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'note',
], function ($router) {
    Route::post('/createNote', [NoteController::class, 'createNote'])->middleware('permission:إنشاء ملاحظة');
    Route::get('/getNotesByStudentId/{studentId}', [NoteController::class, 'getNotesByStudentId'])->middleware('permission:عرض ملاحظات الطالب');
    Route::delete('/deleteNote/{noteId}', [NoteController::class, 'deleteNote'])->middleware('permission:حذف ملاحظة');
    Route::get('/getMyNotes', [NoteController::class, 'getMyNotes'])->middleware('permission:عرض ملاحظاتي');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'sabr',
], function ($router) {
    Route::post('/createSabr', [SabrController::class, 'createSabr'])->middleware('permission:إنشاء سبر');
    Route::get('/getSabrById/{id}', [SabrController::class, 'getSabrById'])->middleware('permission:عرض سبر الطالب');
    Route::post('/updateSabrResult/{id}', [SabrController::class, 'updateResult'])->middleware('permission:تعديل نتيجة السبر');
    Route::get('/getMySabrs', [SabrController::class, 'getMySabrs'])->middleware('permission:عرض سبري');
    Route::get('/getAllSabrs', [SabrController::class, 'getAllSabrs'])->middleware('permission:عرض كافة السبور');
    Route::delete('/deleteSabr/{id}', [SabrController::class, 'deleteSabr'])->middleware('permission:حذف سبر');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'memorization',
], function ($router) {
    Route::post('/createMemorization', [MemorizationController::class, 'createMemorization'])->middleware('permission:إنشاء تسميع');
    Route::get('/getMemorizationById/{id}', [MemorizationController::class, 'getMemorizationById'])->middleware('permission:عرض تسميع الطالب');
    Route::delete('/deleteMemorization/{id}', [MemorizationController::class, 'deleteMemorization'])->middleware('permission:حذف تسميع');
    Route::get('/getMyMemorizations', [MemorizationController::class, 'getMyMemorizations'])->middleware('permission:عرض تسميعاتي');
    Route::get('/getAllMemorizations', [MemorizationController::class, 'getAllMemorizations'])->middleware('permission:عرض كافة التسميعات');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'warning',
], function ($router) {
    Route::post('/createWarning', [WarningController::class, 'createWarning'])->middleware('permission:إنشاء إنذار');
    Route::get('/getWarningById/{id}', [WarningController::class, 'getWarningById'])->middleware('permission:عرض تفاصيل الإنذار');
    Route::delete('/deleteWarning/{id}', [WarningController::class, 'deleteWarning'])->middleware('permission:حذف إنذار');
    Route::get('/getAllWarnings', [WarningController::class, 'getAllWarnings'])->middleware('permission:عرض كافة الإنذارات');
    Route::get('/getMyWarnings', [WarningController::class, 'getMyWarnings'])->middleware('permission:عرض إنذاراتي');

});
Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'exam',
], function ($router) {
    Route::post('/createExam', [ExamController::class, 'createExam'])->middleware('permission:إنشاء امتحان');
    Route::get('/getExamById/{id}', [ExamController::class, 'getExamById'])->middleware('permission:عرض تفاصيل الامتحان');
    Route::delete('/deleteExam/{id}', [ExamController::class, 'deleteExam'])->middleware('permission:حذف امتحان');
    Route::get('/getAllExams', [ExamController::class, 'getAllExams'])->middleware('permission:عرض كافة الامتحانات');
    Route::post('/updateExam/{id}', [ExamController::class, 'updateExam'])->middleware('permission:تعديل الامتحان');
    Route::get('/myExams', [ExamController::class, 'myExams'])->middleware('permission:امتحاناتي');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'absence',
], function ($router) {
    Route::post('/createAbsence', [StudentCourseAbsenceController::class, 'createAbsence']);
    Route::get('/getAbsenceById/{id}', [StudentCourseAbsenceController::class, 'getAbsenceById']);
    Route::post('/updateAbsence/{id}', [StudentCourseAbsenceController::class, 'updateAbsence']);
    Route::delete('/deleteAbsence/{id}', [StudentCourseAbsenceController::class, 'deleteAbsence']);
    Route::get('/getAllAbsences', [StudentCourseAbsenceController::class, 'getAllAbsence']);

});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
], function ($router) {
    Route::get('/courses-students', [ReportApiController::class, 'getCoursesStudents']);
    Route::get('/student-info', [ReportApiController::class, 'getStudentInfo']);
    Route::get('/courses-dates-lessons', [ReportApiController::class, 'getCoursesDatesLessons']);
});
