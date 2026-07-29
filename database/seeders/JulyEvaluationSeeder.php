<?php

namespace Database\Seeders;

use App\Models\AdministrationBehaviorObservation;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\EvaluationCandidate;
use App\Models\Exam;
use App\Models\Memorization;
use App\Models\Note;
use App\Models\Project;
use App\Models\Sabr;
use App\Models\Student;
use App\Models\StudentCourseAbsence;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warning;
use App\Services\Evaluation\EvaluationCandidateSyncService;
use App\Services\Evaluation\EvaluationCycleService;
use App\Services\Evaluation\EvaluationInputService;
use App\Services\Evaluation\EvaluationRunService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JulyEvaluationSeeder extends Seeder
{
    private const JULY_CYCLE_NAME = 'التقييم التجريبي الكامل - تموز 2026';

    public function run(): void
    {
        $this->command->info('  ✅ إنشاء دورة تقييم متكاملة لشهر تموز 2026...');

        $actor = User::query()
            ->where('email', 'superadmin@gmail.com')
            ->firstOrFail();
        $project = Project::query()
            ->where('name', 'مشروع إتقان القراءة')
            ->firstOrFail();
        $courses = Course::query()
            ->where('project_id', $project->id)
            ->whereIn('name', ['دورة الإتقان الأولى', 'دورة الإتقان الثانية'])
            ->get()
            ->keyBy('name');

        if ($courses->count() !== 2) {
            throw new RuntimeException('تعذر العثور على مقرري مشروع إتقان القراءة اللازمين لبيانات تموز.');
        }

        $firstCourse = $courses->get('دورة الإتقان الأولى');
        $secondCourse = $courses->get('دورة الإتقان الثانية');
        $firstCourse->update(['end_date' => '2026-07-31']);

        $scenarios = collect([
            'hammam-nasser' => [
                'course' => $firstCourse,
                'attendance' => [
                    'present', 'present', 'present', 'present',
                    'present', 'present', 'present', 'present',
                ],
                'quran_pages' => [
                    1 => [570, 571, 572],
                    2 => [573, 574, 575],
                ],
                'exam_scores' => [0 => 96, 1 => 92],
                'administration_deduction' => 1,
                'sabr' => [
                    ['type' => 'داخلي', 'date' => '2026-07-10', 'parts' => [26]],
                    ['type' => 'أوقاف', 'date' => '2026-07-24', 'parts' => [27]],
                ],
            ],
            'kenan-alhouri' => [
                'course' => $firstCourse,
                'attendance' => [
                    'present', 'present', 'first_period', 'present',
                    'present', 'full_excused', 'present', 'present',
                ],
                'quran_pages' => [
                    1 => [560, 561],
                    2 => [562, 563, 564],
                ],
                'exam_scores' => [0 => 88, 1 => 84],
                'administration_deduction' => 1,
                'warning' => [
                    'title' => 'تنبيه إداري - تأخر صباحي',
                    'description' => 'تأخر الطالب عن بداية إحدى جلسات تموز وتمت متابعته تربوياً.',
                    'deduction_points' => 2,
                    'occurred_at' => '2026-07-09 18:00:00',
                ],
                'sabr' => [
                    ['type' => 'داخلي', 'date' => '2026-07-21', 'parts' => [25]],
                ],
            ],
            'hussein-alhouri' => [
                'course' => $firstCourse,
                'attendance' => [
                    'present', 'full', 'present', 'second_period',
                    'present', 'first_period', 'present', 'present',
                ],
                'quran_pages' => [
                    1 => [550, 551, 552],
                    2 => [553, 554, 555],
                ],
                'exam_scores' => [0 => 72, 1 => 68],
                'administration_deduction' => 3,
                'sabr' => [
                    ['type' => 'داخلي', 'date' => '2026-07-18', 'parts' => [24]],
                ],
            ],
            'abdalrahman-salem' => [
                'course' => $secondCourse,
                'attendance' => [
                    'present', 'full', 'present', 'present',
                    'full_excused', 'full', 'first_period', 'present',
                ],
                'quran_pages' => [
                    1 => [540, 541],
                    2 => [542, 543, 544],
                ],
                'exam_scores' => [0 => 82, 1 => 60],
                'administration_deduction' => 4,
                'warning' => [
                    'title' => 'إنذار إداري - غياب غير مبرر',
                    'description' => 'سُجل غياب غير مبرر خلال الفترة الثانية وتم التواصل مع ولي الأمر.',
                    'deduction_points' => 5,
                    'occurred_at' => '2026-07-23 18:00:00',
                ],
                'sabr' => [
                    ['type' => 'أوقاف', 'date' => '2026-07-25', 'parts' => [3]],
                ],
            ],
        ]);

        $students = Student::query()
            ->whereIn('username', $scenarios->keys())
            ->get()
            ->keyBy('username');

        if ($students->count() !== $scenarios->count()) {
            throw new RuntimeException('تعذر العثور على جميع طلاب سيناريو تقييم تموز.');
        }

        foreach ($scenarios as $username => $scenario) {
            $students->get($username)->update([
                'mosque_id' => $scenario['course']->mosque_id,
            ]);
        }

        $cycle = app(EvaluationCycleService::class)->create([
            'project_id' => $project->id,
            'name' => self::JULY_CYCLE_NAME,
            'season' => 'summer',
            'top_students_count' => 4,
            'course_ids' => [$firstCourse->id, $secondCourse->id],
            'periods' => [
                [
                    'name' => 'الفترة الأولى من تموز',
                    'sequence' => 1,
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-07-14',
                ],
                [
                    'name' => 'الفترة الثانية من تموز',
                    'sequence' => 2,
                    'start_date' => '2026-07-15',
                    'end_date' => '2026-07-28',
                ],
            ],
        ], $actor);

        app(EvaluationCandidateSyncService::class)->sync($cycle);
        $cycle = app(EvaluationCycleService::class)->transition(
            $cycle,
            'data_collection',
            $actor
        );
        $periods = $cycle->periods->keyBy('sequence');
        $sessions = $this->createSessions($courses);
        $candidates = EvaluationCandidate::query()
            ->with(['cycle', 'enrollments'])
            ->where('evaluation_cycle_id', $cycle->id)
            ->get()
            ->keyBy('student_id');

        foreach ($scenarios as $username => $scenario) {
            $student = $students->get($username);
            $candidate = $candidates->get($student->id);
            if (! $candidate) {
                throw new RuntimeException("لم تتم مزامنة الطالب {$username} ضمن دورة تموز.");
            }

            $enrollment = $candidate->enrollments
                ->firstWhere('course_id', $scenario['course']->id);
            if (! $enrollment) {
                throw new RuntimeException("لا يوجد تسجيل مجمد للطالب {$username} في مقرر تموز.");
            }

            $courseSessions = $sessions->get($scenario['course']->id);
            $this->createAttendance(
                $student,
                $enrollment->circle_id,
                $scenario,
                $courseSessions,
                $actor
            );
            $this->createJulyNotes($student, $enrollment->teacher_id, $periods);
            $this->createQuranRecords(
                $student,
                $enrollment->circle_id,
                $enrollment->teacher_id,
                $scenario,
                $courseSessions,
                $periods
            );
            $this->createExamRecords($student, $scenario);
            $this->createWarning($student, $scenario, $actor);
            $this->createSabrRecords($student, $scenario, $enrollment->teacher_id);
            $this->createAdministrationObservation(
                $candidate,
                $scenario,
                $periods->get(2)->id,
                $actor
            );
        }

        $this->call(JulyEvaluationCriteriaSeeder::class);

        $readiness = app(EvaluationRunService::class)->readiness($cycle);
        if (! $readiness['is_ready']) {
            $notReady = collect($readiness['candidates'])
                ->where('is_ready', false)
                ->pluck('student_name')
                ->implode('، ');
            throw new RuntimeException(
                'بيانات تقييم تموز غير مكتملة للطلاب: '.$notReady
            );
        }

        $preview = app(EvaluationRunService::class)->run($cycle, $actor, true);

        $this->command->info(
            "     ├── {$readiness['candidate_count']} طلاب مكتملو الجاهزية"
        );
        $this->command->info('     ├── 16 جلسة فعلية و32 سجل حضور تفصيلي');
        $this->command->info('     ├── 7 معايير محفوظة لكل نتيجة');
        $this->command->info(
            "     └── معاينة النتائج رقم {$preview->sequence} جاهزة للمراجعة"
        );
    }

    private function createSessions(Collection $courses): Collection
    {
        $sessionDates = [
            'دورة الإتقان الأولى' => [
                '2026-07-01', '2026-07-05', '2026-07-08', '2026-07-12',
                '2026-07-15', '2026-07-19', '2026-07-22', '2026-07-26',
            ],
            'دورة الإتقان الثانية' => [
                '2026-07-02', '2026-07-06', '2026-07-09', '2026-07-13',
                '2026-07-16', '2026-07-20', '2026-07-23', '2026-07-27',
            ],
        ];
        $sessions = collect();

        foreach ($sessionDates as $courseName => $dates) {
            $course = $courses->get($courseName);
            $courseSessions = collect();

            foreach ($dates as $date) {
                $session = CourseDate::create([
                    'course_id' => $course->id,
                    'session_date' => $date,
                    'status' => 'held',
                    'counts_for_attendance' => true,
                    'held_at' => $date.' 20:00:00',
                    'cancellation_reason' => null,
                ]);
                $courseSessions->put($date, $session);
            }

            $sessions->put($course->id, $courseSessions);
        }

        return $sessions;
    }

    private function createAttendance(
        Student $student,
        int $circleId,
        array $scenario,
        Collection $sessions,
        User $actor
    ): void {
        foreach ($sessions->values() as $index => $session) {
            $attendanceType = $scenario['attendance'][$index];
            $isExcused = $attendanceType === 'full_excused';
            $type = $isExcused ? 'full' : $attendanceType;
            $date = $session->session_date->toDateString();
            $note = match (true) {
                $isExcused => 'غياب بعذر موثق من ولي الأمر.',
                $type === 'full' => 'غياب كامل دون عذر.',
                $type === 'first_period' => 'تأخر عن الفترة الأولى.',
                $type === 'second_period' => 'انصراف قبل الفترة الثانية.',
                default => null,
            };

            StudentCourseAbsence::create([
                'student' => $student->id,
                'course' => $scenario['course']->id,
                'course_date_id' => $session->id,
                'circle_id' => $circleId,
                'note' => $note,
                'type' => $type,
                'is_excused' => $isExcused,
                'source' => 'july_evaluation_seed',
                'external_reference' => "JULY-2026-{$student->username}-{$date}",
                'captured_at' => $date.' 20:05:00',
                'recorded_by' => $actor->id,
                'date' => $date,
            ]);
        }
    }

    private function createJulyNotes(
        Student $student,
        int $teacherId,
        Collection $periods
    ): void {
        foreach ($periods as $period) {
            $date = $period->sequence === 1
                ? '2026-07-12 20:30:00'
                : '2026-07-26 20:30:00';
            $note = Note::create([
                'student_id' => $student->id,
                'user_id' => $teacherId,
                'title' => "متابعة {$period->name}",
                'description' => 'متابعة تربوية موثقة تشمل الحضور، التفاعل، الواجب، ومستوى القراءة والحفظ.',
            ]);
            DB::table('notes')->where('id', $note->id)->update([
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    private function createQuranRecords(
        Student $student,
        int $circleId,
        int $teacherId,
        array $scenario,
        Collection $sessions,
        Collection $periods
    ): void {
        foreach ($scenario['quran_pages'] as $sequence => $pages) {
            $period = $periods->get($sequence);
            $periodSessions = $sessions->filter(
                fn (CourseDate $session) => $session->session_date->betweenIncluded(
                    $period->start_date,
                    $period->end_date
                )
            )->values();

            foreach ($pages as $index => $page) {
                $session = $periodSessions->get($index % $periodSessions->count());
                Memorization::create([
                    'student' => $student->id,
                    'giver' => $teacherId,
                    'course_id' => $scenario['course']->id,
                    'circle_id' => $circleId,
                    'course_date_id' => $session->id,
                    'record_type' => 'memorization',
                    'recorded_at' => $session->session_date->toDateString().' 19:00:00',
                    'page_number' => $page,
                    'name' => "تسميع الصفحة {$page}",
                ]);
            }

            $revisionSession = $periodSessions->last();
            Memorization::create([
                'student' => $student->id,
                'giver' => $teacherId,
                'course_id' => $scenario['course']->id,
                'circle_id' => $circleId,
                'course_date_id' => $revisionSession->id,
                'record_type' => 'revision',
                'recorded_at' => $revisionSession->session_date->toDateString().' 19:20:00',
                'page_number' => $pages[0],
                'name' => "مراجعة الصفحة {$pages[0]}",
            ]);
        }
    }

    private function createExamRecords(Student $student, array $scenario): void
    {
        $subjects = Subject::query()
            ->where('course_id', $scenario['course']->id)
            ->orderBy('id')
            ->get()
            ->values();

        foreach ($scenario['exam_scores'] as $subjectOffset => $score) {
            $subject = $subjects->get($subjectOffset);
            if (! $subject) {
                throw new RuntimeException('تعذر مطابقة مادة اختبار تموز مع مقررها.');
            }

            $exam = Exam::updateOrCreate(
                [
                    'student' => $student->id,
                    'subject' => $subject->id,
                    'course' => $scenario['course']->id,
                ],
                ['mark' => $score]
            );
            DB::table('exams')->where('id', $exam->id)->update([
                'created_at' => '2026-07-14 18:30:00',
                'updated_at' => '2026-07-14 18:30:00',
            ]);
        }
    }

    private function createWarning(
        Student $student,
        array $scenario,
        User $actor
    ): void {
        if (! isset($scenario['warning'])) {
            return;
        }

        $warningData = $scenario['warning'];
        $occurredAt = $warningData['occurred_at'];
        unset($warningData['occurred_at']);
        $warningData['student'] = $student->id;
        $warningData['warner'] = $actor->id;
        $warning = Warning::create($warningData);
        DB::table('warnings')->where('id', $warning->id)->update([
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    private function createSabrRecords(
        Student $student,
        array $scenario,
        int $teacherId
    ): void {
        foreach ($scenario['sabr'] as $assessment) {
            Sabr::create([
                'student' => $student->id,
                'giver' => $teacherId,
                'course' => $scenario['course']->id,
                'value' => 'ممتاز',
                'type' => $assessment['type'],
                'date' => $assessment['date'],
                'parts' => $assessment['parts'],
                'note' => 'سبر ناجح موثق ضمن دورة تقييم تموز 2026.',
            ]);
        }
    }

    private function createAdministrationObservation(
        EvaluationCandidate $candidate,
        array $scenario,
        int $periodId,
        User $actor
    ): void {
        $observation = AdministrationBehaviorObservation::create([
            'evaluation_candidate_id' => $candidate->id,
            'evaluation_period_id' => $periodId,
            'context_type' => 'monthly_behavior',
            'description' => 'ملاحظة إدارية تربوية معتمدة ضمن دورة تقييم تموز.',
            'deduction_points' => $scenario['administration_deduction'],
            'occurred_at' => '2026-07-25 17:30:00',
            'observed_by' => $actor->id,
            'status' => 'pending',
        ]);

        app(EvaluationInputService::class)
            ->approveAdministrationObservation($observation, $actor);
    }
}
