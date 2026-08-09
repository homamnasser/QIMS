<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseCurriculumController;
use App\Http\Controllers\CourseDateController;
use App\Http\Controllers\EvaluationAuditController;
use App\Http\Controllers\EvaluationCycleController;
use App\Http\Controllers\EvaluationInputController;
use App\Http\Controllers\EvaluationRunController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MemorizationController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\MosqueController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\RecognitionController;
use App\Http\Controllers\ReportApiController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SabrController;
use App\Http\Controllers\StudentCircleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ReadingImprovementController;
use App\Http\Controllers\StudentCourseAbsenceController;
use App\Http\Controllers\StudentFinalResultController;
use App\Http\Controllers\StudentLearningController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\WarningController;
use App\Http\Controllers\WebAuthenticationController;
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

Route::prefix('web/auth')
    ->middleware('frontend.request')
    ->group(function (): void {
        Route::post('/login', [WebAuthenticationController::class, 'login'])
            ->middleware('throttle:web-login');
        Route::middleware(['auth:sanctum', 'auth.channel:web'])
            ->group(function (): void {
                Route::get('/me', [WebAuthenticationController::class, 'me']);
                Route::post('/logout', [WebAuthenticationController::class, 'logout']);
            });
    });

Route::prefix('mobile/auth')->group(function (): void {
    Route::post('/login', [MobileAuthenticationController::class, 'login'])
        ->middleware('throttle:mobile-login');
    Route::get('/me', [MobileAuthenticationController::class, 'me'])
        ->middleware(['auth:sanctum', 'auth.channel:mobile-access']);
    Route::post('/refresh', [MobileAuthenticationController::class, 'refresh'])
        ->middleware([
            'auth:sanctum',
            'auth.channel:mobile-refresh',
            'throttle:mobile-refresh',
        ]);
    Route::post('/logout', [MobileAuthenticationController::class, 'logout'])
        ->middleware(['auth:sanctum', 'auth.channel:mobile-token']);
});

Route::prefix('public/surveys')->group(function (): void {
    Route::get('/{publicToken}', [PublicSurveyController::class, 'show']);
    Route::post('/{publicToken}/identify', [PublicSurveyController::class, 'identify']);
    Route::post('/{publicToken}/responses', [PublicSurveyController::class, 'submit']);
});

// مسار عام بلا مصادقة؛ نحدّ معدل الطلبات لمنع الإرهاق الآلي (60 طلبًا/دقيقة لكل IP).
// المدقّق الشرعي يمسح رمزًا أو رمزين فلا يقترب من الحد. لا أثر على إصدار الشهادات.
Route::get('/public/certificates/verify/{token}', [CertificateController::class, 'verify'])
    ->middleware('throttle:60,1');

$webAuthenticatedMiddleware = [
    'auth:sanctum',
    'auth.channel:web',
    'staff.mosque.scope',
];
$mobileStudentMiddleware = ['auth:sanctum', 'auth.channel:mobile-student'];
$mobileStaffMiddleware = [
    'auth:sanctum',
    'auth.channel:mobile-staff',
    'staff.mosque.scope',
];

Route::middleware($webAuthenticatedMiddleware)->prefix('surveys')->group(function (): void {
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
    'middleware' => $webAuthenticatedMiddleware,
], function ($router) {
    Route::post('/createStaffMember', [AuthController::class, 'createStaffMember'])->middleware('permission:إنشاء موظف');
    Route::post('/updateStaffMember/{id}', [AuthController::class, 'updateStaffMember'])->middleware('permission:تعديل موظف');
    Route::delete('/deleteStaffMember/{id}', [AuthController::class, 'deleteStaffMember'])->middleware('permission:حذف موظف');
    Route::get('/getStaffById/{id}', [AuthController::class, 'getStaffById'])->middleware('permission:عرض تفاصيل الموظف');
    Route::get('/getAllStaff', [AuthController::class, 'getAllStaff'])->middleware('permission:عرض كافة الموظفين');
    Route::get('/staffScopeOptions', [AuthController::class, 'staffScopeOptions'])
        ->middleware('permission:إنشاء موظف|تعديل موظف');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
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
    'middleware' => $webAuthenticatedMiddleware,
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
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'mosque',
], function ($router) {
    Route::post('/createMosque', [MosqueController::class, 'createMosque'])->middleware('permission:إنشاء مسجد');
    Route::get('/getAllMosques', [MosqueController::class, 'getAllMosques'])->middleware('permission:عرض كافة المساجد');
    Route::get('/getMosque/{id}', [MosqueController::class, 'getMosque'])->middleware('permission:عرض تفاصيل المسجد');
    Route::post('/updateMosque/{id}', [MosqueController::class, 'updateMosque'])->middleware('permission:تعديل مسجد');
    Route::delete('/deleteMosque/{id}', [MosqueController::class, 'deleteMosque'])->middleware('permission:حذف مسجد');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
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
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'subject',
], function ($router) {
    Route::post('/createSubject', [SubjectController::class, 'createSubject'])->middleware('permission:إنشاء مادة');
    Route::get('/getAllSubjects', [SubjectController::class, 'getAllSubjects'])->middleware('permission:عرض كافة المواد');
    Route::get('/getSubject/{id}', [SubjectController::class, 'getSubject'])->middleware('permission:عرض تفاصيل المادة');
    Route::post('/updateSubject/{id}', [SubjectController::class, 'updateSubject'])->middleware('permission:تعديل المادة');
    Route::delete('/deleteSubject/{id}', [SubjectController::class, 'deleteSubject'])->middleware('permission:حذف مادة');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'lesson',
], function ($router) {
    Route::post('/createLesson', [LessonController::class, 'createLesson'])->middleware('permission:إنشاء درس');
    Route::get('/getAllLessons', [LessonController::class, 'getAllLessons'])->middleware('permission:عرض كافة الدروس');
    Route::get('/getLesson/{id}', [LessonController::class, 'getLesson'])->middleware('permission:عرض تفاصيل الدرس');
    Route::post('/updateLesson/{id}', [LessonController::class, 'updateLesson'])->middleware('permission:تعديل الدرس');
    Route::delete('/deleteLesson/{id}', [LessonController::class, 'deleteLesson'])->middleware('permission:حذف درس');
});
Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'courseDate',
], function ($router) {
    Route::post('/createCourseDate', [CourseDateController::class, 'createCourseDate'])->middleware('permission:إنشاء تاريخ الكورس');
    Route::get('/getDatesByCourse/{courseId}', [CourseDateController::class, 'getDateByCourse'])->middleware('permission:عرض تواريخ الكورس');
    Route::delete('/deleteDate/{id}', [CourseDateController::class, 'deleteDate'])->middleware('permission:حذف تاريخ');
    Route::post('/createDateManual', [CourseDateController::class, 'createDateManual'])->middleware('permission:إضافة تاريخ يدوياً');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'dateLesson',
], function ($router) {
    Route::post('/assignLessonsToDate', [CourseCurriculumController::class, 'assignLessonsToDate'])->middleware('permission:إسناد الدروس للتاريخ');
    Route::post('/updateAssignLessonsToDate/{courseDate}', [CourseCurriculumController::class, 'updateAssignLessonsToDate'])->middleware('permission:تعديل إسناد دروس التاريخ');
    Route::delete('/detachAllLessons/{courseDateId}', [CourseCurriculumController::class, 'deleteLessonsFromDate'])->middleware('permission:حذف دروس التاريخ');
    Route::get('/getLessonsByDate/{id}', [CourseCurriculumController::class, 'getLessonsByDate'])->middleware('permission:عرض دروس التاريخ');
    Route::get('/getCurriculumByCourse/{courseId}', [CourseCurriculumController::class, 'getCurriculumByCourse'])->middleware('permission:عرض المنهج الدراسي للكورس');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
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
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'student',
], function ($router) {
    Route::post('/createStudent', [StudentController::class, 'createStudent'])->middleware('permission:إنشاء طالب');
    Route::get('/getStudentById/{id}', [StudentController::class, 'getStudentById'])->middleware('permission:عرض تفاصيل الطالب');
    Route::get('/getAllStudents', [StudentController::class, 'getAllStudents'])->middleware('permission:عرض كافة الطلاب');
    Route::post('/updateStudent/{id}', [StudentController::class, 'updateStudent'])->middleware('permission:تعديل الطالب');
    Route::delete('/deleteStudent/{id}', [StudentController::class, 'deleteStudent'])->middleware('permission:حذف الطالب');
});

Route::middleware($webAuthenticatedMiddleware)
    ->prefix('evaluation-cycles')
    ->group(function (): void {
        Route::get('/', [EvaluationCycleController::class, 'index'])
            ->middleware('permission:عرض دورات التقييم');
        Route::post('/', [EvaluationCycleController::class, 'store'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/rule-template', [EvaluationCycleController::class, 'ruleTemplate'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/rule-profiles', [EvaluationCycleController::class, 'ruleProfiles'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::post('/rule-profiles', [EvaluationCycleController::class, 'storeRuleProfile'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/{cycle}', [EvaluationCycleController::class, 'show'])
            ->middleware('permission:عرض دورات التقييم');
        Route::put('/{cycle}', [EvaluationCycleController::class, 'update'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::post('/{cycle}/sync-candidates', [EvaluationCycleController::class, 'syncCandidates'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/{cycle}/readiness', [EvaluationCycleController::class, 'readiness'])
            ->middleware('permission:عرض تقييمات الطلاب النهائية');
        Route::get('/{cycle}/audit-events', [EvaluationAuditController::class, 'index'])
            ->middleware('permission:عرض سجل تدقيق التقييم');
        Route::get('/{cycle}/recognition', [RecognitionController::class, 'show'])
            ->middleware('permission:إدارة التكريم النهائي');
        Route::put('/{cycle}/status', [EvaluationCycleController::class, 'transition'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::post('/{cycle}/runs', [EvaluationRunController::class, 'store'])
            ->middleware('permission:احتساب النتائج النهائية');
    });

Route::middleware($webAuthenticatedMiddleware)
    ->prefix('evaluation-candidates')
    ->group(function (): void {
        Route::get('/{candidate}/review', [EvaluationInputController::class, 'review'])
            ->middleware('permission:إدخال تقييم المدرس|عرض تقييمات الطلاب النهائية');
        Route::put('/{candidate}/teacher-evaluation', [EvaluationInputController::class, 'teacher'])
            ->middleware('permission:إدخال تقييم المدرس');
        Route::put('/{candidate}/quran-assessment', [EvaluationInputController::class, 'quran'])
            ->middleware('permission:إدخال تقييم القرآن');
    });

Route::middleware($mobileStaffMiddleware)
    ->prefix('mobile/teacher/evaluation-candidates')
    ->group(function (): void {
        Route::get('/', [EvaluationInputController::class, 'teacherCandidates'])
            ->middleware('permission:إدخال تقييم المدرس');
        Route::get('/{candidate}/review', [EvaluationInputController::class, 'review'])
            ->middleware('permission:إدخال تقييم المدرس|عرض تقييمات الطلاب النهائية');
        Route::put('/{candidate}/teacher-evaluation', [EvaluationInputController::class, 'teacher'])
            ->middleware('permission:إدخال تقييم المدرس');
        Route::put('/{candidate}/quran-assessment', [EvaluationInputController::class, 'quran'])
            ->middleware('permission:إدخال تقييم القرآن');
    });

Route::middleware($webAuthenticatedMiddleware)
    ->prefix('evaluation-runs')
    ->group(function (): void {
        Route::get('/{run}', [EvaluationRunController::class, 'show'])
            ->middleware('permission:عرض تقييمات الطلاب النهائية');
        Route::post('/{run}/approve', [EvaluationRunController::class, 'approve'])
            ->middleware('permission:اعتماد النتائج النهائية');
        Route::post('/{run}/publish', [EvaluationRunController::class, 'publish'])
            ->middleware('permission:نشر النتائج النهائية');
        Route::post('/{run}/certificates', [CertificateController::class, 'issueBatch'])
            ->middleware('permission:إصدار الشهادات النهائية');
    });

Route::post('/evaluation-results/{result}/certificate', [CertificateController::class, 'issue'])
    ->middleware([
        ...$webAuthenticatedMiddleware,
        'permission:إصدار الشهادات النهائية',
    ]);

Route::middleware($webAuthenticatedMiddleware)
    ->prefix('certificates')
    ->group(function (): void {
        Route::get('/{certificate}/download', [CertificateController::class, 'download'])
            ->middleware('permission:عرض تقييمات الطلاب النهائية');
        Route::post('/{certificate}/revoke', [CertificateController::class, 'revoke'])
            ->middleware('permission:إصدار الشهادات النهائية');
    });

Route::middleware($webAuthenticatedMiddleware)
    ->prefix('recognition-batches')
    ->group(function (): void {
        Route::post('/{batch}/approve', [RecognitionController::class, 'approve'])
            ->middleware('permission:إدارة التكريم النهائي');
        Route::post('/{batch}/publish', [RecognitionController::class, 'publish'])
            ->middleware('permission:إدارة التكريم النهائي');
    });

Route::group([
    'middleware' => $mobileStudentMiddleware,
    'prefix' => 'mobile/student/me',
], function (): void {
    Route::get('/mosque', [StudentLearningController::class, 'mosque'])
        ->middleware('permission:'.config('roles.student_capabilities.mosque'));
    Route::get('/circles', [StudentLearningController::class, 'circles'])
        ->middleware('permission:'.config('roles.student_capabilities.circles'));
    Route::get('/courses', [StudentLearningController::class, 'courses'])
        ->middleware('permission:'.config('roles.student_capabilities.courses'));
    Route::get('/courses/{courseId}/schedule', [StudentLearningController::class, 'courseSchedule'])
        ->whereNumber('courseId')
        ->middleware('permission:'.config('roles.student_capabilities.course_schedules'));
    Route::get('/notes', [StudentLearningController::class, 'notes'])
        ->middleware('permission:'.config('roles.student_capabilities.notes'));
    Route::get('/sabrs', [StudentLearningController::class, 'sabrs'])
        ->middleware('permission:'.config('roles.student_capabilities.sabrs'));
    Route::get('/memorizations', [StudentLearningController::class, 'memorizations'])
        ->middleware('permission:'.config('roles.student_capabilities.memorizations'));
    Route::get('/warnings', [StudentLearningController::class, 'warnings'])
        ->middleware('permission:'.config('roles.student_capabilities.warnings'));
    Route::get('/exams', [StudentLearningController::class, 'exams'])
        ->middleware('permission:'.config('roles.student_capabilities.exams'));
    Route::get('/reading-improvements', [StudentLearningController::class, 'readingImprovements'])
        ->middleware('permission:'.config('roles.student_capabilities.reading_improvements'));
    Route::get('/final-results', [StudentFinalResultController::class, 'index'])
        ->middleware('permission:'.config('roles.student_capabilities.final_results'));
    Route::get('/final-results/{resultId}', [StudentFinalResultController::class, 'show'])
        ->whereNumber('resultId')
        ->middleware('permission:'.config('roles.student_capabilities.final_results'));
    Route::get('/certificates/{certificateId}', [CertificateController::class, 'studentDownload'])
        ->whereNumber('certificateId')
        ->middleware('permission:'.config('roles.student_capabilities.certificates'));
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'studentCircle',
], function ($router) {
    Route::post('/addStudentsToCircle', [StudentCircleController::class, 'addStudents'])->middleware('permission:إضافة طلاب للحلقة');
    Route::post('/removeStudentFromCircle', [StudentCircleController::class, 'removeStudent'])->middleware('permission:إلغاء التحاق طالب بالحلقة');
    Route::get('/getStudentsByCircle/{circleId}', [StudentCircleController::class, 'getStudents'])->middleware('permission:عرض طلاب الحلقة');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'note',
], function ($router) {
    Route::post('/createNote', [NoteController::class, 'createNote'])->middleware('permission:إنشاء ملاحظة');
    Route::get('/getNotesByStudentId/{studentId}', [NoteController::class, 'getNotesByStudentId'])->middleware('permission:عرض ملاحظات الطالب');
    Route::get('/getAllNotes', [NoteController::class, 'getAllNotes'])->middleware('permission:عرض ملاحظات الطالب');
    Route::delete('/deleteNote/{noteId}', [NoteController::class, 'deleteNote'])->middleware('permission:حذف ملاحظة');
    Route::get('/getMyNotes', [NoteController::class, 'getMyNotes'])->middleware('permission:عرض ملاحظاتي');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
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
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'memorization',
], function ($router) {
    Route::post('/createMemorization', [MemorizationController::class, 'createMemorization'])->middleware('permission:إنشاء تسميع');
    Route::get('/getMemorizationById/{id}', [MemorizationController::class, 'getMemorizationById'])->middleware('permission:عرض تسميع الطالب');
    Route::delete('/deleteMemorization/{id}', [MemorizationController::class, 'deleteMemorization'])->middleware('permission:حذف تسميع');
    Route::get('/getMyMemorizations', [MemorizationController::class, 'getMyMemorizations'])->middleware('permission:عرض تسميعاتي');
    Route::get('/getAllMemorizations', [MemorizationController::class, 'getAllMemorizations'])->middleware('permission:عرض كافة التسميعات');
});

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'warning',
], function ($router) {
    Route::post('/createWarning', [WarningController::class, 'createWarning'])->middleware('permission:إنشاء إنذار');
    Route::get('/getWarningById/{id}', [WarningController::class, 'getWarningById'])->middleware('permission:عرض تفاصيل الإنذار');
    Route::delete('/deleteWarning/{id}', [WarningController::class, 'deleteWarning'])->middleware('permission:حذف إنذار');
    Route::get('/getAllWarnings', [WarningController::class, 'getAllWarnings'])->middleware('permission:عرض كافة الإنذارات');
    Route::get('/getMyWarnings', [WarningController::class, 'getMyWarnings'])->middleware('permission:عرض إنذاراتي');

});
Route::group([
    'middleware' => $webAuthenticatedMiddleware,
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
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'absence',
], function ($router) {
    Route::post('/createAbsence', [StudentCourseAbsenceController::class, 'createAbsence'])->middleware('permission:إنشاء غياب');
    Route::get('/getAbsenceById/{id}', [StudentCourseAbsenceController::class, 'getAbsenceById'])->middleware('permission:عرض تفاصيل الغياب');
    Route::post('/updateAbsence/{id}', [StudentCourseAbsenceController::class, 'updateAbsence'])->middleware('permission:تعديل الغياب');
    Route::delete('/deleteAbsence/{id}', [StudentCourseAbsenceController::class, 'deleteAbsence'])->middleware('permission:حذف غياب');
    Route::get('/getAllAbsences', [StudentCourseAbsenceController::class, 'getAllAbsence'])->middleware('permission:عرض كافة الغيابات');

});

/*
 * تقييم التحسن في القراءة — أحد معايير التقييم النهائي الستة.
 * المتحكّم كان مكتملاً بلا مسار، فكان المعيار يبقى «غير جاهز» بلا أي طريق
 * لإنشاء سجله من الواجهة.
 */
Route::group([
    'middleware' => $webAuthenticatedMiddleware,
    'prefix' => 'reading-improvement',
], function ($router) {
    Route::get('/getAllReadingImprovements', [ReadingImprovementController::class, 'index'])->middleware('permission:عرض كافة تقييمات القراءة');
    Route::post('/createReadingImprovement', [ReadingImprovementController::class, 'store'])->middleware('permission:إنشاء تقييم قراءة');
    Route::get('/getReadingImprovementById/{id}', [ReadingImprovementController::class, 'show'])->middleware('permission:عرض تفاصيل تقييم القراءة');
    Route::post('/updateReadingImprovement/{readingImprovement}', [ReadingImprovementController::class, 'update'])->middleware('permission:تعديل تقييم القراءة');
    Route::delete('/deleteReadingImprovement/{readingImprovement}', [ReadingImprovementController::class, 'destroy'])->middleware('permission:حذف تقييم القراءة');
});

Route::middleware($mobileStaffMiddleware)
    ->prefix('mobile/staff')
    ->group(function (): void {
        Route::get('/courses', [CourseController::class, 'getAllCourses'])
            ->middleware('permission:عرض كافة الكورسات');
        Route::get('/courses/{id}', [CourseController::class, 'getCourse'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الكورس');
        Route::get('/subjects', [SubjectController::class, 'getAllSubjects'])
            ->middleware('permission:عرض كافة المواد');
        Route::get('/subjects/{id}', [SubjectController::class, 'getSubject'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل المادة');
        Route::get('/lessons', [LessonController::class, 'getAllLessons'])
            ->middleware('permission:عرض كافة الدروس');
        Route::get('/lessons/{id}', [LessonController::class, 'getLesson'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الدرس');
        Route::get('/courses/{courseId}/dates', [CourseDateController::class, 'getDateByCourse'])
            ->whereNumber('courseId')
            ->middleware('permission:عرض تواريخ الكورس');
        Route::get('/course-dates/{id}/lessons', [CourseCurriculumController::class, 'getLessonsByDate'])
            ->whereNumber('id')
            ->middleware('permission:عرض دروس التاريخ');
        Route::get('/courses/{courseId}/curriculum', [CourseCurriculumController::class, 'getCurriculumByCourse'])
            ->whereNumber('courseId')
            ->middleware('permission:عرض المنهج الدراسي للكورس');
        Route::get('/circles/mine/curriculum', [CircleController::class, 'getMyCircleCurriculum'])
            ->middleware('permission:عرض منهج حلقتي');
        Route::get('/circles', [CircleController::class, 'getAllCircles'])
            ->middleware('permission:عرض كافة الحلقات');
        Route::get('/circles/{id}', [CircleController::class, 'getCircle'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الحلقة');
        Route::get('/circles/{circleId}/students', [StudentCircleController::class, 'getStudents'])
            ->whereNumber('circleId')
            ->middleware('permission:عرض طلاب الحلقة');
        Route::get('/students', [StudentController::class, 'getAllStudents'])
            ->middleware('permission:عرض كافة الطلاب');
        Route::get('/students/{id}', [StudentController::class, 'getStudentById'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الطالب');

        Route::get('/attendance', [StudentCourseAbsenceController::class, 'getAllAbsence'])
            ->middleware('permission:عرض كافة الغيابات');
        Route::post('/attendance', [StudentCourseAbsenceController::class, 'createAbsence'])
            ->middleware('permission:إنشاء غياب');
        Route::get('/attendance/{id}', [StudentCourseAbsenceController::class, 'getAbsenceById'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الغياب');
        Route::put('/attendance/{id}', [StudentCourseAbsenceController::class, 'updateAbsence'])
            ->whereNumber('id')
            ->middleware('permission:تعديل الغياب');
        Route::delete('/attendance/{id}', [StudentCourseAbsenceController::class, 'deleteAbsence'])
            ->whereNumber('id')
            ->middleware('permission:حذف غياب');

        Route::get('/warnings/mine', [WarningController::class, 'getMyWarnings'])
            ->middleware('permission:عرض إنذاراتي');
        Route::get('/warnings', [WarningController::class, 'getAllWarnings'])
            ->middleware('permission:عرض كافة الإنذارات');
        Route::post('/warnings', [WarningController::class, 'createWarning'])
            ->middleware('permission:إنشاء إنذار');
        Route::get('/warnings/{id}', [WarningController::class, 'getWarningById'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الإنذار');
        Route::delete('/warnings/{id}', [WarningController::class, 'deleteWarning'])
            ->whereNumber('id')
            ->middleware('permission:حذف إنذار');

        Route::get('/notes/mine', [NoteController::class, 'getMyNotes'])
            ->middleware('permission:عرض ملاحظاتي');
        Route::get('/notes/students/{studentId}', [NoteController::class, 'getNotesByStudentId'])
            ->whereNumber('studentId')
            ->middleware('permission:عرض ملاحظات الطالب');
        Route::post('/notes', [NoteController::class, 'createNote'])
            ->middleware('permission:إنشاء ملاحظة');
        Route::delete('/notes/{noteId}', [NoteController::class, 'deleteNote'])
            ->whereNumber('noteId')
            ->middleware('permission:حذف ملاحظة');

        Route::get('/sabrs/mine', [SabrController::class, 'getMySabrs'])
            ->middleware('permission:عرض سبري');
        Route::get('/sabrs', [SabrController::class, 'getAllSabrs'])
            ->middleware('permission:عرض كافة السبور');
        Route::post('/sabrs', [SabrController::class, 'createSabr'])
            ->middleware('permission:إنشاء سبر');
        Route::get('/sabrs/{id}', [SabrController::class, 'getSabrById'])
            ->whereNumber('id')
            ->middleware('permission:عرض سبر الطالب');
        Route::put('/sabrs/{id}', [SabrController::class, 'updateResult'])
            ->whereNumber('id')
            ->middleware('permission:تعديل نتيجة السبر');
        Route::delete('/sabrs/{id}', [SabrController::class, 'deleteSabr'])
            ->whereNumber('id')
            ->middleware('permission:حذف سبر');

        Route::get('/memorizations/mine', [MemorizationController::class, 'getMyMemorizations'])
            ->middleware('permission:عرض تسميعاتي');
        Route::get('/memorizations', [MemorizationController::class, 'getAllMemorizations'])
            ->middleware('permission:عرض كافة التسميعات');
        Route::post('/memorizations', [MemorizationController::class, 'createMemorization'])
            ->middleware('permission:إنشاء تسميع');
        Route::get('/memorizations/{id}', [MemorizationController::class, 'getMemorizationById'])
            ->whereNumber('id')
            ->middleware('permission:عرض تسميع الطالب');
        Route::delete('/memorizations/{id}', [MemorizationController::class, 'deleteMemorization'])
            ->whereNumber('id')
            ->middleware('permission:حذف تسميع');

        // نفس متحكّم الويب ونفس أسماء الصلاحيات؛ ما يتغيّر هو قناة المصادقة فقط.
        Route::get('/reading-improvements', [ReadingImprovementController::class, 'index'])
            ->middleware('permission:عرض كافة تقييمات القراءة');
        Route::post('/reading-improvements', [ReadingImprovementController::class, 'store'])
            ->middleware('permission:إنشاء تقييم قراءة');
        Route::get('/reading-improvements/{id}', [ReadingImprovementController::class, 'show'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل تقييم القراءة');
        Route::put('/reading-improvements/{readingImprovement}', [ReadingImprovementController::class, 'update'])
            ->whereNumber('readingImprovement')
            ->middleware('permission:تعديل تقييم القراءة');
        Route::delete('/reading-improvements/{readingImprovement}', [ReadingImprovementController::class, 'destroy'])
            ->whereNumber('readingImprovement')
            ->middleware('permission:حذف تقييم القراءة');

        Route::get('/exams', [ExamController::class, 'getAllExams'])
            ->middleware('permission:عرض كافة الامتحانات');
        Route::post('/exams', [ExamController::class, 'createExam'])
            ->middleware('permission:إنشاء امتحان');
        Route::get('/exams/mine', [ExamController::class, 'myExams'])
            ->middleware('permission:امتحاناتي');
        Route::get('/exams/{id}', [ExamController::class, 'getExamById'])
            ->whereNumber('id')
            ->middleware('permission:عرض تفاصيل الامتحان');
        Route::put('/exams/{id}', [ExamController::class, 'updateExam'])
            ->whereNumber('id')
            ->middleware('permission:تعديل الامتحان');
        Route::delete('/exams/{id}', [ExamController::class, 'deleteExam'])
            ->whereNumber('id')
            ->middleware('permission:حذف امتحان');

        Route::get('/evaluation-cycles', [EvaluationCycleController::class, 'index'])
            ->middleware('permission:عرض دورات التقييم');
        Route::post('/evaluation-cycles', [EvaluationCycleController::class, 'store'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/evaluation-cycles/rule-template', [EvaluationCycleController::class, 'ruleTemplate'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/evaluation-cycles/rule-profiles', [EvaluationCycleController::class, 'ruleProfiles'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::post('/evaluation-cycles/rule-profiles', [EvaluationCycleController::class, 'storeRuleProfile'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/evaluation-cycles/{cycle}', [EvaluationCycleController::class, 'show'])
            ->middleware('permission:عرض دورات التقييم');
        Route::put('/evaluation-cycles/{cycle}', [EvaluationCycleController::class, 'update'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::post('/evaluation-cycles/{cycle}/sync-candidates', [EvaluationCycleController::class, 'syncCandidates'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::get('/evaluation-cycles/{cycle}/readiness', [EvaluationCycleController::class, 'readiness'])
            ->middleware('permission:عرض تقييمات الطلاب النهائية');
        Route::get('/evaluation-cycles/{cycle}/audit-events', [EvaluationAuditController::class, 'index'])
            ->middleware('permission:عرض سجل تدقيق التقييم');
        Route::get('/evaluation-cycles/{cycle}/recognition', [RecognitionController::class, 'show'])
            ->middleware('permission:إدارة التكريم النهائي');
        Route::put('/evaluation-cycles/{cycle}/status', [EvaluationCycleController::class, 'transition'])
            ->middleware('permission:إدارة دورات التقييم');
        Route::post('/evaluation-cycles/{cycle}/runs', [EvaluationRunController::class, 'store'])
            ->middleware('permission:احتساب النتائج النهائية');

        Route::get('/evaluation-candidates', [EvaluationInputController::class, 'teacherCandidates'])
            ->middleware('permission:إدخال تقييم المدرس|عرض تقييمات الطلاب النهائية');
        Route::get('/evaluation-candidates/{candidate}/review', [EvaluationInputController::class, 'review'])
            ->middleware('permission:إدخال تقييم المدرس|عرض تقييمات الطلاب النهائية');
        Route::put('/evaluation-candidates/{candidate}/teacher-evaluation', [EvaluationInputController::class, 'teacher'])
            ->middleware('permission:إدخال تقييم المدرس');
        Route::put('/evaluation-candidates/{candidate}/quran-assessment', [EvaluationInputController::class, 'quran'])
            ->middleware('permission:إدخال تقييم القرآن');

        Route::get('/evaluation-runs/{run}', [EvaluationRunController::class, 'show'])
            ->middleware('permission:عرض تقييمات الطلاب النهائية');
        Route::post('/evaluation-runs/{run}/approve', [EvaluationRunController::class, 'approve'])
            ->middleware('permission:اعتماد النتائج النهائية');
        Route::post('/evaluation-runs/{run}/publish', [EvaluationRunController::class, 'publish'])
            ->middleware('permission:نشر النتائج النهائية');
        Route::post('/evaluation-runs/{run}/certificates', [CertificateController::class, 'issueBatch'])
            ->middleware('permission:إصدار الشهادات النهائية');
        Route::post('/evaluation-results/{result}/certificate', [CertificateController::class, 'issue'])
            ->middleware('permission:إصدار الشهادات النهائية');
        Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])
            ->middleware('permission:عرض تقييمات الطلاب النهائية');
        Route::post('/certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])
            ->middleware('permission:إصدار الشهادات النهائية');
        Route::post('/recognition-batches/{batch}/approve', [RecognitionController::class, 'approve'])
            ->middleware('permission:إدارة التكريم النهائي');
        Route::post('/recognition-batches/{batch}/publish', [RecognitionController::class, 'publish'])
            ->middleware('permission:إدارة التكريم النهائي');
    });

Route::group([
    'middleware' => $webAuthenticatedMiddleware,
], function ($router) {
    Route::get('/courses-students', [ReportApiController::class, 'getCoursesStudents']);
    Route::get('/student-info', [ReportApiController::class, 'getStudentInfo']);
    Route::get('/courses-dates-lessons', [ReportApiController::class, 'getCoursesDatesLessons']);
});
