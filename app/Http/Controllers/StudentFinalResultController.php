<?php

namespace App\Http\Controllers;

use App\Models\EvaluationResult;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFinalResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $results = EvaluationResult::query()
            ->where('status', 'published')
            ->whereHas(
                'candidate',
                fn ($query) => $query->where('student_id', $student->id)
            )
            ->with([
                'run.cycle:id,name,season,start_date,end_date,published_at',
                'candidate:id,evaluation_cycle_id,student_id',
                'certificates' => fn ($query) => $query->where('status', 'issued'),
            ])
            ->latest('published_at')
            ->get();

        return response()->json(['data' => $results]);
    }

    public function show(Request $request, int $resultId): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $result = EvaluationResult::query()
            ->whereKey($resultId)
            ->where('status', 'published')
            ->whereHas(
                'candidate',
                fn ($query) => $query->where('student_id', $student->id)
            )
            ->with([
                'run.cycle:id,name,season,start_date,end_date,published_at',
                'candidate.student:id,first_name,last_name,selfnumber',
                'criteria',
                'certificates' => fn ($query) => $query->where('status', 'issued'),
            ])
            ->firstOrFail();

        return response()->json(['data' => $result]);
    }
}
