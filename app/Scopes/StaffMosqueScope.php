<?php

namespace App\Scopes;

use App\Enums\StaffWorkScope;
use App\Models\AdministrationBehaviorObservation;
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
use App\Support\StaffScopeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class StaffMosqueScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $staff = app(StaffScopeContext::class)->staff();

        if (
            ! $staff instanceof User
            || $staff->work_scope !== StaffWorkScope::Mosque
            || ! $staff->mosque_id
        ) {
            return;
        }

        $mosqueId = (int) $staff->mosque_id;
        $modelClass = $model::class;

        if ($modelClass === Mosque::class) {
            $builder->where($model->qualifyColumn($model->getKeyName()), $mosqueId);

            return;
        }

        if (in_array($modelClass, [
            Course::class,
            Student::class,
            StudentSelfNumberReservation::class,
            EvaluationCandidate::class,
            Survey::class,
        ], true)) {
            $builder->where($model->qualifyColumn('mosque_id'), $mosqueId);

            return;
        }

        if ($modelClass === User::class) {
            $builder
                ->where(
                    $model->qualifyColumn('work_scope'),
                    StaffWorkScope::Mosque->value
                )
                ->where($model->qualifyColumn('mosque_id'), $mosqueId);

            return;
        }

        $relations = match ($modelClass) {
            Project::class => ['courses'],
            Subject::class => ['course'],
            Lesson::class => ['subject'],
            CourseDate::class => ['course'],
            Circle::class => ['course'],
            StudentCircle::class => ['studentDetails', 'circleDetails'],
            Note::class => ['student'],
            Sabr::class => ['studentDetails', 'courseDetails'],
            Memorization::class => ['studentDetails', 'course', 'circle'],
            Warning::class => ['studentDetails'],
            Exam::class => ['studentDetails', 'subjectDetails', 'courseDetails'],
            StudentCourseAbsence::class => ['studentDetails', 'courseDetails'],
            StudentMark::class => ['studentDetails', 'courseDetails', 'circleDetails'],
            ReadingImprovement::class => ['studentDetails', 'courseDetails'],
            EvaluationPolicy::class => ['cycles'],
            EvaluationCycle::class => ['courses'],
            EvaluationPeriod::class => ['cycle'],
            EvaluationCandidateEnrollment::class => ['candidate', 'course', 'circle'],
            EvaluationExamResult::class => ['candidate', 'subject'],
            TeacherPeriodEvaluation::class,
            QuranPeriodAssessment::class,
            SabrPartAchievement::class,
            AdministrationBehaviorObservation::class => ['candidate'],
            EvaluationRun::class => ['cycle'],
            EvaluationResult::class => ['candidate'],
            EvaluationCriterionResult::class,
            Certificate::class,
            RecognitionAward::class => ['result'],
            RecognitionBatch::class => ['cycle'],
            EvaluationAuditEvent::class => ['cycle'],
            SurveySection::class,
            SurveyQuestion::class,
            SurveyStudentField::class,
            SurveyLogicRule::class => ['survey'],
            SurveyQuestionOption::class => ['question'],
            SurveyResponse::class => ['survey', 'student'],
            SurveyAnswer::class => ['response'],
            SurveyResponseFile::class => ['response'],
            default => [],
        };

        foreach ($relations as $relation) {
            // Each related model carries this scope as well, so whereHas()
            // composes the complete path back to the staff member's mosque.
            $builder->whereHas($relation);
        }
    }
}
