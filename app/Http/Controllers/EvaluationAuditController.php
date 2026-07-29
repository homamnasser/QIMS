<?php

namespace App\Http\Controllers;

use App\Models\EvaluationAuditEvent;
use App\Models\EvaluationCycle;
use App\Services\Evaluation\EvaluationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationAuditController extends Controller
{
    public function __construct(private readonly EvaluationAccessService $access) {}

    public function index(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canApproveCycle($request->user(), $cycle), 403);
        $data = $request->validate([
            'event_type' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = EvaluationAuditEvent::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->when($data['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($data['actor_id'] ?? null, fn ($query, $actor) => $query->where('actor_id', $actor))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->where('occurred_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->where('occurred_at', '<=', $to))
            ->latest('occurred_at')
            ->paginate($data['per_page'] ?? 30);

        return response()->json($events);
    }
}
