<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\MosqueController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\CourseDateController;
use App\Http\Controllers\CourseCurriculumController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\StudentController;
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

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|admin'],
], function ($router) {
    Route::post('/createStaffMember', [AuthController::class, 'createStaffMember']);
    Route::post('/updateStaffMember/{id}', [AuthController::class, 'updateStaffMember']);
    Route::delete('/deleteStaffMember/{id}', [AuthController::class, 'deleteStaffMember']);
    Route::get('/getStaffById/{id}', [AuthController::class, 'getStaffById']);
    Route::get('/getAllStaff', [AuthController::class, 'getAllStaff']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'role'
], function ($router) {
    Route::post('/createRole', [RoleController::class, 'createRole']);
    Route::get('/getAllRoles', [RoleController::class, 'getAllRoles']);
    Route::get('/getRole/{id}', [RoleController::class, 'getRole']);
    Route::post('/updateRole/{id}', [RoleController::class, 'updateRole']);
    Route::delete('/deleteRole/{id}', [RoleController::class, 'deleteRole']);
    Route::get('/getAllPermissions', [RoleController::class, 'getAllPermissions']);
});


Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|supervisor|admin'],
    'prefix' => 'project'
], function ($router) {
    Route::post('/createProject', [ProjectController::class, 'createProject']);
    Route::get('/getAllProjects', [ProjectController::class, 'getAllProjects']);
    Route::get('/getProject/{id}', [ProjectController::class, 'getProject'])->middleware('permission:getProject');
    Route::post('/updateProject/{id}', [ProjectController::class, 'updateProject']);
    Route::delete('/deleteProject/{id}', [ProjectController::class, 'deleteProject']);
    Route::post('/editProjectStatus/{id}', [ProjectController::class, 'editProjectStatus']);
    Route::get('/getMyProjects', [ProjectController::class, 'getMyProjects']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'mosque'
], function ($router) {
    Route::post('/createMosque', [MosqueController::class, 'createMosque']);
    Route::get('/getAllMosques', [MosqueController::class, 'getAllMosques']);
    Route::get('/getMosque/{id}', [MosqueController::class, 'getMosque']);
    Route::post('/updateMosque/{id}', [MosqueController::class, 'updateMosque']);
    Route::delete('/deleteMosque/{id}', [MosqueController::class, 'deleteMosque']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|admin|supervisor'],
    'prefix' => 'course'
], function ($router) {
    Route::post('/createCourse', [CourseController::class, 'createCourse']);
    Route::get('/getAllCourses', [CourseController::class, 'getAllCourses']);
    Route::get('/getCourse/{id}', [CourseController::class, 'getCourse']);
    Route::post('/updateCourse/{id}', [CourseController::class, 'updateCourse']);
    Route::delete('/deleteCourse/{id}', [CourseController::class, 'deleteCourse']);
    Route::post('/editCourseStatus/{id}', [CourseController::class, 'editCourseStatus']);
    Route::get('/getMyCourses', [CourseController::class, 'getMyCourses']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'subject'
], function ($router) {
    Route::post('/createSubject', [SubjectController::class, 'createSubject']);
    Route::get('/getAllSubjects', [SubjectController::class, 'getAllSubjects']);
    Route::get('/getSubject/{id}', [SubjectController::class, 'getSubject']);
    Route::post('/updateSubject/{id}', [SubjectController::class, 'updateSubject']);
    Route::delete('/deleteSubject/{id}', [SubjectController::class, 'deleteSubject']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'lesson'
], function ($router) {
    Route::post('/createLesson', [LessonController::class, 'createLesson']);
    Route::get('/getAllLessons', [LessonController::class, 'getAllLessons']);
    Route::get('/getLesson/{id}', [LessonController::class, 'getLesson']);
    Route::post('/updateLesson/{id}', [LessonController::class, 'updateLesson']);
    Route::delete('/deleteLesson/{id}', [LessonController::class, 'deleteLesson']);
});
Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'courseDate'
], function ($router) {
    Route::post('/createCourseDate', [CourseDateController::class, 'createCourseDate']);
    Route::get('/getDatesByCourse/{courseId}', [CourseDateController::class, 'getDateByCourse']);
    Route::delete('/deleteDate/{id}', [CourseDateController::class, 'deleteDate']);
    Route::post('/createDateManual', [CourseDateController::class, 'createDateManual']);
});


Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin'],
    'prefix' => 'dateLesson'
], function ($router) {
    Route::post('/assignLessonsToDate', [CourseCurriculumController::class, 'assignLessonsToDate']);
    Route::post('/updateAssignLessonsToDate/{courseDate}', [CourseCurriculumController::class, 'updateAssignLessonsToDate']);
    Route::delete('/detachAllLessons/{courseDateId}', [CourseCurriculumController::class, 'deleteLessonsFromDate']);
    Route::get('/getLessonsByDate/{id}', [CourseCurriculumController::class, 'getLessonsByDate']);
    Route::get('/getCurriculumByCourse/{courseId}', [CourseCurriculumController::class, 'getCurriculumByCourse']);

});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|admin|supervisor'],
    'prefix' => 'circle'
], function ($router) {
    Route::post('/createCircle', [CircleController::class, 'createCircle']);
    Route::get('/getMyCircleCurriculum', [CircleController::class, 'getMyCircleCurriculum']);
    Route::get('/getCircle/{id}', [CircleController::class, 'getCircle']);
    Route::get('/getAllCircles', [CircleController::class, 'getAllCircles']);
    Route::delete('/deleteCircle/{id}', [CircleController::class, 'deleteCircle']);
    Route::post('/updateCircle/{id}', [CircleController::class, 'updateCircle']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|admin'],
    'prefix' => 'student'
], function ($router) {
    Route::post('/createStudent', [StudentController::class, 'createStudent']);
    Route::get('/getStudentById/{id}', [StudentController::class, 'getStudentById']);
    Route::get('/getAllStudents', [StudentController::class, 'getAllStudents']);
    Route::post('/updateStudent/{id}', [StudentController::class, 'updateStudent']);
    Route::delete('/deleteStudent/{id}', [StudentController::class, 'deleteStudent']);
});


