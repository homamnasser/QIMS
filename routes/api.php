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
use App\Http\Controllers\StudentCircleController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\SabrController;
use App\Http\Controllers\MemorizationController;
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
    'middleware' => ['api', 'auth:sanctum'],
], function ($router) {
    Route::post('/createStaffMember', [AuthController::class, 'createStaffMember'])->middleware('permission:createStaffMember');
    Route::post('/updateStaffMember/{id}', [AuthController::class, 'updateStaffMember'])->middleware('permission:updateStaffMember');
    Route::delete('/deleteStaffMember/{id}', [AuthController::class, 'deleteStaffMember'])->middleware('permission:deleteStaff');
    Route::get('/getStaffById/{id}', [AuthController::class, 'getStaffById'])->middleware('permission:getStaffById');
    Route::get('/getAllStaff', [AuthController::class, 'getAllStaff'])->middleware('permission:getAllStaff');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'role'
], function ($router) {
    Route::post('/createRole', [RoleController::class, 'createRole'])->middleware('permission:createRole');
    Route::get('/getAllRoles', [RoleController::class, 'getAllRoles'])->middleware('permission:getAllRoles');
    Route::get('/getRole/{id}', [RoleController::class, 'getRole'])->middleware('permission:getRole');
    Route::post('/updateRole/{id}', [RoleController::class, 'updateRole'])->middleware('permission:updateRole');
    Route::delete('/deleteRole/{id}', [RoleController::class, 'deleteRole'])->middleware('permission:deleteRole');
    Route::get('/getAllPermissions', [RoleController::class, 'getAllPermissions'])->middleware('permission:getAllPermissions');
});


Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'project'
], function ($router) {
    Route::post('/createProject', [ProjectController::class, 'createProject'])->middleware('permission:createProject');
    Route::get('/getAllProjects', [ProjectController::class, 'getAllProjects'])->middleware('permission:getAllProjects');
    Route::get('/getProject/{id}', [ProjectController::class, 'getProject'])->middleware('permission:getProject');
    Route::post('/updateProject/{id}', [ProjectController::class, 'updateProject'])->middleware('permission:updateProject');
    Route::delete('/deleteProject/{id}', [ProjectController::class, 'deleteProject'])->middleware('permission:deleteProject');
    Route::post('/editProjectStatus/{id}', [ProjectController::class, 'editProjectStatus'])->middleware('permission:editProjectStatus');
    Route::get('/getMyProjects', [ProjectController::class, 'getMyProjects']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'mosque'
], function ($router) {
    Route::post('/createMosque', [MosqueController::class, 'createMosque'])->middleware('permission:createMosque');
    Route::get('/getAllMosques', [MosqueController::class, 'getAllMosques'])->middleware('permission:getAllMosques');
    Route::get('/getMosque/{id}', [MosqueController::class, 'getMosque'])->middleware('permission:getMosque');
    Route::post('/updateMosque/{id}', [MosqueController::class, 'updateMosque'])->middleware('permission:updateMosque');
    Route::delete('/deleteMosque/{id}', [MosqueController::class, 'deleteMosque'])->middleware('permission:deleteMosque');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'course'
], function ($router) {
    Route::post('/createCourse', [CourseController::class, 'createCourse'])->middleware('permission:createCourse');
    Route::get('/getAllCourses', [CourseController::class, 'getAllCourses'])->middleware('permission:getAllCourses');
    Route::get('/getCourse/{id}', [CourseController::class, 'getCourse'])->middleware('permission:getCourse');
    Route::post('/updateCourse/{id}', [CourseController::class, 'updateCourse'])->middleware('permission:updateCourse');
    Route::delete('/deleteCourse/{id}', [CourseController::class, 'deleteCourse'])->middleware('permission:deleteCourse');
    Route::post('/editCourseStatus/{id}', [CourseController::class, 'editCourseStatus'])->middleware('permission:editCourseStatus');
    Route::get('/getMyCourses', [CourseController::class, 'getMyCourses']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'subject'
], function ($router) {
    Route::post('/createSubject', [SubjectController::class, 'createSubject'])->middleware('permission:createSubject');
    Route::get('/getAllSubjects', [SubjectController::class, 'getAllSubjects'])->middleware('permission:getAllSubjects');
    Route::get('/getSubject/{id}', [SubjectController::class, 'getSubject'])->middleware('permission:getSubject');
    Route::post('/updateSubject/{id}', [SubjectController::class, 'updateSubject'])->middleware('permission:updateSubject');
    Route::delete('/deleteSubject/{id}', [SubjectController::class, 'deleteSubject'])->middleware('permission:deleteSubject');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'lesson'
], function ($router) {
    Route::post('/createLesson', [LessonController::class, 'createLesson'])->middleware('permission:createLesson');
    Route::get('/getAllLessons', [LessonController::class, 'getAllLessons'])->middleware('permission:getAllLessons');
    Route::get('/getLesson/{id}', [LessonController::class, 'getLesson'])->middleware('permission:getLesson');
    Route::post('/updateLesson/{id}', [LessonController::class, 'updateLesson'])->middleware('permission:updateLesson');
    Route::delete('/deleteLesson/{id}', [LessonController::class, 'deleteLesson'])->middleware('permission:deleteLesson');
});
Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'courseDate'
], function ($router) {
    Route::post('/createCourseDate', [CourseDateController::class, 'createCourseDate'])->middleware('permission:createCourseDate');
    Route::get('/getDatesByCourse/{courseId}', [CourseDateController::class, 'getDateByCourse'])->middleware('permission:getDatesByCourse');
    Route::delete('/deleteDate/{id}', [CourseDateController::class, 'deleteDate'])->middleware('permission:deleteDate');
    Route::post('/createDateManual', [CourseDateController::class, 'createDateManual'])->middleware('permission:createDateManual');
});


Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'dateLesson'
], function ($router) {
    Route::post('/assignLessonsToDate', [CourseCurriculumController::class, 'assignLessonsToDate'])->middleware('permission:assignLessonsToDate');
    Route::post('/updateAssignLessonsToDate/{courseDate}', [CourseCurriculumController::class, 'updateAssignLessonsToDate'])->middleware('permission:updateAssignLessonsToDate');
    Route::delete('/detachAllLessons/{courseDateId}', [CourseCurriculumController::class, 'deleteLessonsFromDate'])->middleware('permission:deleteLessonsFromDate');
    Route::get('/getLessonsByDate/{id}', [CourseCurriculumController::class, 'getLessonsByDate'])->middleware('permission:getLessonsByDate');
    Route::get('/getCurriculumByCourse/{courseId}', [CourseCurriculumController::class, 'getCurriculumByCourse'])->middleware('permission:getCurriculumByCourse');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'circle'
], function ($router) {
    Route::post('/createCircle', [CircleController::class, 'createCircle'])->middleware('permission:createCircle');
    Route::get('/getMyCircleCurriculum', [CircleController::class, 'getMyCircleCurriculum'])->middleware('permission:getMyCircleCurriculum');
    Route::get('/getCircle/{id}', [CircleController::class, 'getCircle'])->middleware('permission:getCircleById');
    Route::get('/getAllCircles', [CircleController::class, 'getAllCircles'])->middleware('permission:getAllCircles');
    Route::delete('/deleteCircle/{id}', [CircleController::class, 'deleteCircle'])->middleware('permission:deleteCircle');
    Route::post('/updateCircle/{id}', [CircleController::class, 'updateCircle'])->middleware('permission:updateCircle');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'student'
], function ($router) {
    Route::post('/createStudent', [StudentController::class, 'createStudent'])->middleware('permission:createStudent');
    Route::get('/getStudentById/{id}', [StudentController::class, 'getStudentById'])->middleware('permission:getStudentById');
    Route::get('/getAllStudents', [StudentController::class, 'getAllStudents'])->middleware('permission:getAllStudents');
    Route::post('/updateStudent/{id}', [StudentController::class, 'updateStudent'])->middleware('permission:updateStudent');
    Route::delete('/deleteStudent/{id}', [StudentController::class, 'deleteStudent'])->middleware('permission:deleteStudent');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'studentCircle'
], function ($router) {
    Route::post('/addStudentsToCircle', [StudentCircleController::class, 'addStudents'])->middleware('permission:addStudentsToCircle');
    Route::post('/removeStudentFromCircle', [StudentCircleController::class, 'removeStudent'])->middleware('permission:removeStudentFromCircle');
    Route::get('/getStudentsByCircle/{circleId}', [StudentCircleController::class, 'getStudents'])->middleware('permission:getStudentsByCircle');
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|admin|supervisor|teacher'],
    'prefix' => 'note'
], function ($router) {
    Route::post('/createNote', [NoteController::class, 'createNote']);
    Route::get('/getNotesByStudentId/{studentId}', [NoteController::class, 'getNotesByStudentId']);
    Route::delete('/deleteNote/{noteId}', [NoteController::class, 'deleteNote']);
    Route::get('/getMyNotes', [NoteController::class, 'getMyNotes']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum', 'role:super-admin|teacher|student'],
    'prefix' => 'sabr'
], function ($router) {
    Route::post('/createSabr', [SabrController::class, 'createSabr']);
    Route::get('/getSabrById/{id}', [SabrController::class, 'getSabrById']);
    Route::post('/updateSabrResult/{id}', [SabrController::class, 'updateResult']);
    Route::get('/getMySabrs', [SabrController::class, 'getMySabrs']);
    Route::get('/getAllSabrs', [SabrController::class, 'getAllSabrs']);
    Route::delete('/deleteSabr/{id}', [SabrController::class, 'deleteSabr']);
});

Route::group([
    'middleware' => ['api', 'auth:sanctum'],
    'prefix' => 'memorization'
], function ($router) {
    Route::post('/createMemorization', [MemorizationController::class, 'createMemorization']);
    Route::get('/getMemorizationById/{id}', [MemorizationController::class, 'getMemorizationById']);
    Route::delete('/deleteMemorization/{id}', [MemorizationController::class, 'deleteMemorization']);
    Route::get('/getMyMemorizations', [MemorizationController::class, 'getMyMemorizations']);
    Route::get('/getAllMemorizations', [MemorizationController::class, 'getAllMemorizations']);
});
