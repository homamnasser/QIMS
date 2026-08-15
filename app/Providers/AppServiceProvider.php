<?php

namespace App\Providers;

use App\IService\ICircleService;
use App\IService\ICourseCurriculumService;
use App\IService\ICourseDateService;
use App\IService\ICourseService;
use App\IService\IExamService;
use App\IService\ILessonService;
use App\IService\IMarkService;
use App\IService\IMemorizationService;
use App\IService\IMobileAuthenticationService;
use App\IService\IMosqueService;
use App\IService\INoteService;
use App\IService\IProjectService;
use App\IService\IReadingImprovementService;
use App\IService\IRoleService;
use App\IService\ISabrService;
use App\IService\IStaffService;
use App\IService\IStudentCircleService;
use App\IService\IStudentCourseAbsenceService;
use App\IService\IStudentLearningService;
use App\IService\IStudentService;
use App\IService\ISubjectService;
use App\IService\IWarningService;
use App\Models\Certificate;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\EvaluationAuditEvent;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationCriterionResult;
use App\Models\EvaluationCycle;
use App\Models\EvaluationExamResult;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationPolicy;
use App\Models\EvaluationResult;
use App\Models\EvaluationRun;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Memorization;
use App\Models\Mosque;
use App\Models\Note;
use App\Models\Project;
use App\Models\QuranPeriodAssessment;
use App\Models\ReadingImprovement;
use App\Models\RecognitionAward;
use App\Models\RecognitionBatch;
use App\Models\Sabr;
use App\Models\SabrPartAchievement;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\StudentCourseAbsence;
use App\Models\StudentMark;
use App\Models\StudentSelfNumberReservation;
use App\Models\Subject;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyLogicRule;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseFile;
use App\Models\SurveySection;
use App\Models\SurveyStudentField;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use App\Models\Warning;
use App\Scopes\StaffMosqueScope;
use App\Services\CircleService;
use App\Services\CourseCurriculumService;
use App\Services\CourseDateService;
use App\Services\CourseService;
use App\Services\ExamService;
use App\Services\LessonService;
use App\Services\MarkService;
use App\Services\MemorizationService;
use App\Services\MobileAuthenticationService;
use App\Services\MosqueService;
use App\Services\NoteService;
use App\Services\ProjectService;
use App\Services\ReadingImprovementService;
use App\Services\RoleService;
use App\Services\SabrService;
use App\Services\StaffService;
use App\Services\StudentCircleService;
use App\Services\StudentCourseAbsenceService;
use App\Services\StudentLearningService;
use App\Services\StudentPushService;
use App\Services\StudentService;
use App\Services\SubjectService;
use App\Services\WarningService;
use App\Support\StaffScopeContext;
use App\Support\StudentPushEvents;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StaffScopeContext::class);
        $this->app->bind(IStaffService::class, StaffService::class);
        $this->app->bind(IRoleService::class, RoleService::class);
        $this->app->bind(IProjectService::class, ProjectService::class);
        $this->app->bind(IReadingImprovementService::class, ReadingImprovementService::class);
        $this->app->bind(IMosqueService::class, MosqueService::class);
        $this->app->bind(ICourseService::class, CourseService::class);
        $this->app->bind(ISubjectService::class, SubjectService::class);
        $this->app->bind(ILessonService::class, LessonService::class);
        $this->app->bind(ICourseDateService::class, CourseDateService::class);
        $this->app->bind(ICourseCurriculumService::class, CourseCurriculumService::class);
        $this->app->bind(ICircleService::class, CircleService::class);
        $this->app->bind(IStudentService::class, StudentService::class);
        $this->app->bind(IStudentLearningService::class, StudentLearningService::class);
        $this->app->bind(IStudentCircleService::class, StudentCircleService::class);
        $this->app->bind(INoteService::class, NoteService::class);
        $this->app->bind(ISabrService::class, SabrService::class);
        $this->app->bind(IMemorizationService::class, MemorizationService::class);
        $this->app->bind(IWarningService::class, WarningService::class);
        $this->app->bind(IExamService::class, ExamService::class);
        $this->app->bind(IStudentCourseAbsenceService::class, StudentCourseAbsenceService::class);
        $this->app->bind(IMarkService::class, MarkService::class);
        $this->app->bind(
            IMobileAuthenticationService::class,
            MobileAuthenticationService::class
        );
    }

    public function boot(): void
    {
        $scope = new StaffMosqueScope;
        $scopedModels = [
            Certificate::class,
            Circle::class,
            Course::class,
            CourseDate::class,
            EvaluationAuditEvent::class,
            EvaluationCandidate::class,
            EvaluationCandidateEnrollment::class,
            EvaluationCriterionResult::class,
            EvaluationCycle::class,
            EvaluationExamResult::class,
            EvaluationPeriod::class,
            EvaluationPolicy::class,
            EvaluationResult::class,
            EvaluationRun::class,
            Exam::class,
            Lesson::class,
            Memorization::class,
            Mosque::class,
            Note::class,
            Project::class,
            QuranPeriodAssessment::class,
            ReadingImprovement::class,
            RecognitionAward::class,
            RecognitionBatch::class,
            Sabr::class,
            SabrPartAchievement::class,
            Student::class,
            StudentCircle::class,
            StudentCourseAbsence::class,
            StudentMark::class,
            StudentSelfNumberReservation::class,
            Subject::class,
            Survey::class,
            SurveyAnswer::class,
            SurveyLogicRule::class,
            SurveyQuestion::class,
            SurveyQuestionOption::class,
            SurveyResponse::class,
            SurveyResponseFile::class,
            SurveySection::class,
            SurveyStudentField::class,
            TeacherPeriodEvaluation::class,
            User::class,
            Warning::class,
        ];

        foreach ($scopedModels as $modelClass) {
            $modelClass::addGlobalScope($scope);
        }

        Gate::before(function ($user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        $this->registerStudentPushHooks();
        $this->configureRateLimiting();
    }

    /**
     * الخطّاف على الموديل لا على المتحكّم: مسارات الويب ومسارات mobile/staff
     * تشترك في المتحكّمات والخدمات نفسها، فخطّاف واحد يغطي القناتين إلى الأبد،
     * بينما الخطّاف على المتحكّمات يعني 7 موديلات × قناتين = 14 موضع تعديل تتباعد.
     */
    private function registerStudentPushHooks(): void
    {
        foreach (StudentPushEvents::ON_CREATE as $model) {
            $model::created(fn ($record) => $this->pushForRecord($record));
        }

        foreach (StudentPushEvents::ON_UPDATE as $model => $column) {
            $model::updated(function ($record) use ($column): void {
                // wasChanged لا wasDirty: نُشعر بعد الحفظ الفعلي، وبتغيّر النتيجة وحدها
                // لا بأي حفظ آخر يمسّ الصف.
                if ($record->wasChanged($column)) {
                    $this->pushForRecord($record);
                }
            });
        }
    }

    private function pushForRecord(object $record): void
    {
        $described = StudentPushEvents::describe($record);

        if (! $described) {
            return;
        }

        [$studentId, $title, $body, $route] = $described;

        if (! $studentId) {
            return;
        }

        StudentPushService::queue($studentId, $title, $body, [
            'route' => $route,
            'id' => $record->getKey(),
        ]);
    }

    /**
     * محدّدات المعدل المسماة التي تشير إليها المسارات عبر middleware الـ throttle.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $account = $request->user();
            $identity = $account
                ? $account::class.':'.$account->getAuthIdentifier()
                : $request->ip();

            return Limit::perMinute(180)->by($identity);
        });

        RateLimiter::for('web-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('login')).'|'.$request->ip()
            );
        });

        // تأكيد الهوية لفتح قسم النتيجة النهائية يفحص كلمة مرور، فهو سطح تخمين.
        // الحساب معروف مسبقاً (الطلب مصادق عليه) فنحدّ به لا بالـ IP وحده،
        // كي لا تُعطّل شبكة مشتركة طلاب مسجد كامل.
        RateLimiter::for('identity-confirm', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->user()
                    ? $request->user()::class.':'.$request->user()->getAuthIdentifier()
                    : $request->ip()
            );
        });

        RateLimiter::for('mobile-refresh', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()
                    ? $request->user()::class.':'.$request->user()->getAuthIdentifier()
                    : $request->ip()
            );
        });
    }
}
