<?php

namespace App\Http\Middleware;

use App\Enums\StaffWorkScope;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Lesson;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\StaffScopeContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnforceStaffMosqueScope
{
    public function __construct(
        private readonly StaffScopeContext $scopeContext
    ) {}

    /**
     * Request fields that can reference mosque-owned entities.
     *
     * @var array<string, class-string<Model>>
     */
    private const REFERENCE_FIELDS = [
        'mosque_id' => Mosque::class,
        'mosque_ids' => Mosque::class,
        'course' => Course::class,
        'course_id' => Course::class,
        'course_ids' => Course::class,
        'circle' => Circle::class,
        'circle_id' => Circle::class,
        'circle_ids' => Circle::class,
        'student' => Student::class,
        'student_id' => Student::class,
        'student_ids' => Student::class,
        'subject' => Subject::class,
        'subject_id' => Subject::class,
        'subject_ids' => Subject::class,
        'course_date_id' => CourseDate::class,
        'course_date_ids' => CourseDate::class,
        'courseDate' => CourseDate::class,
        'courseDateId' => CourseDate::class,
        'lesson_id' => Lesson::class,
        'lesson_ids' => Lesson::class,
        'lessonId' => Lesson::class,
        'lessonIds' => Lesson::class,
        'mosqueId' => Mosque::class,
        'courseId' => Course::class,
        'circleId' => Circle::class,
        'studentId' => Student::class,
        'subjectId' => Subject::class,
        'project' => Project::class,
        'project_id' => Project::class,
        'project_ids' => Project::class,
        'teacher' => User::class,
        'teacher_id' => User::class,
        'teacher_ids' => User::class,
        'giver' => User::class,
        'giver_id' => User::class,
        'warner' => User::class,
        'warner_id' => User::class,
        'supervisor' => User::class,
        'supervisor_id' => User::class,
        'actor_id' => User::class,
        'user_id' => User::class,
        'evaluator_id' => User::class,
    ];

    /**
     * Institute-level mutations cannot be made safely by one-mosque staff.
     *
     * @var list<string>
     */
    private const INSTITUTE_ONLY_MUTATIONS = [
        'role/createRole',
        'role/updateRole/{id}',
        'role/deleteRole/{id}',
        'project/createProject',
        'project/updateProject/{id}',
        'project/deleteProject/{id}',
        'project/editProjectStatus/{id}',
        'mosque/createMosque',
        'mosque/deleteMosque/{id}',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $staff = $request->user();

        if (! $staff instanceof User || ! $staff->isMosqueScoped()) {
            return $next($request);
        }

        $this->scopeContext->activate($staff);

        if (! $staff->mosque_id) {
            $this->scopeContext->clear();

            return $this->forbidden(
                'حساب الموظف مقيّد بمسجد واحد دون تحديد المسجد. راجع مدير المعهد.'
            );
        }

        $uri = preg_replace('#^api/#', '', (string) $request->route()?->uri());
        if (
            ! in_array($request->method(), ['GET', 'HEAD'], true)
            && in_array($uri, self::INSTITUTE_ONLY_MUTATIONS, true)
        ) {
            $this->scopeContext->clear();

            return $this->forbidden(
                'هذه العملية إدارية على مستوى المعهد ولا تتاح للحسابات المقيّدة بمسجد واحد.'
            );
        }

        $this->lockDirectScopeFields($request, $uri, (int) $staff->mosque_id);

        $references = array_merge(
            $request->all(),
            $request->route()?->parameters() ?? []
        );

        if (! $this->referencesAreAccessible($references, $staff)) {
            $this->scopeContext->clear();

            return $this->forbidden(
                'لا يمكنك استخدام أو تعديل بيانات تابعة لمسجد آخر.'
            );
        }

        try {
            return $next($request);
        } finally {
            // Long-running workers must never carry one request's scope into
            // the next request.
            $this->scopeContext->clear();
        }
    }

    private function lockDirectScopeFields(
        Request $request,
        string $uri,
        int $mosqueId
    ): void {
        if (in_array($uri, [
            'createStaffMember',
            'updateStaffMember/{id}',
        ], true)) {
            $request->merge([
                'work_scope' => StaffWorkScope::Mosque->value,
                'mosque_id' => $mosqueId,
            ]);
        }

        if (in_array($uri, [
            'course/createCourse',
            'course/updateCourse/{id}',
            'student/createStudent',
            'student/updateStudent/{id}',
        ], true)) {
            $request->merge(['mosque_id' => $mosqueId]);
        }
    }

    private function referencesAreAccessible(array $input, User $staff): bool
    {
        foreach ($input as $field => $value) {
            if (is_array($value)) {
                if (isset(self::REFERENCE_FIELDS[$field])) {
                    foreach ($this->numericIds($value) as $id) {
                        if (! $this->canAccessReference(self::REFERENCE_FIELDS[$field], $id, $staff)) {
                            return false;
                        }
                    }
                }

                if (! $this->referencesAreAccessible($value, $staff)) {
                    return false;
                }

                continue;
            }

            if (
                isset(self::REFERENCE_FIELDS[$field])
                && is_numeric($value)
                && ! $this->canAccessReference(
                    self::REFERENCE_FIELDS[$field],
                    (int) $value,
                    $staff
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private function numericIds(array $values): array
    {
        $ids = [];
        array_walk_recursive($values, function ($value) use (&$ids): void {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        });

        return array_values(array_unique($ids));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function canAccessReference(
        string $modelClass,
        int $id,
        User $staff
    ): bool {
        if ($modelClass === User::class) {
            return User::query()
                ->whereKey($id)
                ->where('work_scope', StaffWorkScope::Mosque->value)
                ->where('mosque_id', $staff->mosque_id)
                ->exists();
        }

        return $modelClass::query()->whereKey($id)->exists();
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'code' => 403,
            'error_code' => 'MOSQUE_SCOPE_VIOLATION',
            'message' => $message,
            'data' => null,
        ], 403);
    }
}
