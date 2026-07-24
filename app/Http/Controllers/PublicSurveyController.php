<?php

namespace App\Http\Controllers;

use App\Http\Requests\IdentifySurveyStudentRequest;
use App\Http\Requests\SubmitSurveyResponseRequest;
use App\Models\Survey;
use App\Services\SurveyResponseService;
use Illuminate\Http\JsonResponse;

class PublicSurveyController extends Controller
{
    public function __construct(private readonly SurveyResponseService $responseService) {}

    public function show(string $publicToken): JsonResponse
    {
        $survey = $this->survey($publicToken);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب بيانات الاستبيان.',
            'data' => $this->responseService->publicSummary($survey),
        ]);
    }

    public function identify(
        IdentifySurveyStudentRequest $request,
        string $publicToken,
    ): JsonResponse {
        $survey = $this->survey($publicToken);

        return response()->json([
            'code' => 200,
            'message' => 'تم التحقق من الرقم الذاتي. يمكنك تعبئة الاستبيان الآن.',
            'data' => $this->responseService->identify($survey, $request->validated('selfnumber')),
        ]);
    }

    public function submit(
        SubmitSurveyResponseRequest $request,
        string $publicToken,
    ): JsonResponse {
        $survey = $this->survey($publicToken);
        $response = $this->responseService->submit(
            $survey,
            $request->validated(),
            $request->file('files', []),
        );

        return response()->json([
            'code' => 201,
            'message' => 'تم إرسال إجابتك بنجاح. شكرًا لمشاركتك.',
            'data' => [
                'response_token' => $response->response_token,
                'submitted_at' => $response->submitted_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function survey(string $publicToken): Survey
    {
        return Survey::query()->where('public_token', $publicToken)->firstOrFail();
    }
}
