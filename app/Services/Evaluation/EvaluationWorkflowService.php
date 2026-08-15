<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationRun;
use App\Models\User;
use App\Services\StudentPushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationWorkflowService
{
    public function __construct(
        private readonly RecognitionService $recognition,
        private readonly EvaluationAuditService $audit,
    ) {}

    public function approve(EvaluationRun $run, User $actor): EvaluationRun
    {
        if ($run->is_preview || $run->status !== 'completed') {
            throw ValidationException::withMessages([
                'run' => ['يمكن اعتماد تشغيل نهائي مكتمل فقط.'],
            ]);
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->loadMissing('cycle');
            $run->results()->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);
            $run->cycle->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);

            $this->recognition->generate($run, $actor);
            $this->audit->record('evaluation.results_approved', $run, $actor);

            return $run->fresh(['results.criteria', 'cycle']);
        });
    }

    public function publish(EvaluationRun $run, User $actor): EvaluationRun
    {
        $run->loadMissing('cycle');
        if ($run->is_preview || $run->cycle->status !== 'approved') {
            throw ValidationException::withMessages([
                'run' => ['يجب اعتماد النتائج النهائية قبل نشرها.'],
            ]);
        }

        $published = DB::transaction(function () use ($run, $actor) {
            $run->results()->where('status', 'approved')->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            $run->cycle->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);
            $this->audit->record('evaluation.results_published', $run, $actor);

            return $run->fresh(['results.criteria', 'cycle']);
        });

        $this->pushPublishedResults($run);

        return $published;
    }

    /**
     * التحديث الجَماعي أعلاه لا يُطلق أحداث الموديل بحكم تصميمه، فهذا الحدث
     * الوحيد الذي يحتاج نداءً صريحاً — وهو أيضاً الوحيد الذي يتفرّع لمئات الطلاب.
     */
    private function pushPublishedResults(EvaluationRun $run): void
    {
        $studentIds = $run->results()
            ->where('evaluation_results.status', 'published')
            ->join(
                'evaluation_candidates',
                'evaluation_candidates.id',
                '=',
                'evaluation_results.evaluation_candidate_id'
            )
            ->pluck('evaluation_candidates.student_id')
            ->filter()
            ->unique();

        if ($studentIds->isEmpty()) {
            return;
        }

        // المسار نفسه الذي تسلكه بقيّة الإشعارات: صفُّ صندوق فوراً وإرسالٌ بعد
        // الاستجابة، بلا إغلاق ثانٍ يكرّر التقاط الأخطاء والتسجيل.
        //
        // ponytail: إرسال متسلسل بعد الاستجابة. عند اقتراب نشر دورة واحدة من
        // ~١٠٠ طالب يصير max_execution_time خطراً حقيقياً على ذيل القائمة، وعندها
        // يُستبدل الإغلاق داخل StudentPushService::queue() بمهمة على طابور.
        foreach ($studentIds as $studentId) {
            StudentPushService::queue($studentId, 'صدرت نتيجتك النهائية', 'نتيجتك النهائية متاحة الآن.', [
                'route' => '/final-results',
            ]);
        }
    }
}
