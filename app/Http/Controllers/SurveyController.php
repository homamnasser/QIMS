<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSurveyDefinitionRequest;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseFile;
use App\Services\SurveyDefinitionService;
use App\Services\SurveyResponseService;
use App\Services\SurveyStudentFieldRegistry;
use App\Traits\FileTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SurveyController extends Controller
{
    use FileTrait;

    public function __construct(
        private readonly SurveyDefinitionService $definitionService,
        private readonly SurveyResponseService $responseService,
        private readonly SurveyStudentFieldRegistry $fieldRegistry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 12)));
        $surveys = Survey::query()
            ->with('creator:id,first_name,last_name')
            ->withCount('responses')
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($limit)
            ->through(fn (Survey $survey): array => $this->definitionService->serialize($survey, false));

        return $this->paginated($surveys, 'تم جلب قائمة الاستبيانات بنجاح.');
    }

    public function store(StoreSurveyRequest $request): JsonResponse
    {
        $data = $request->safe()->except('logo');
        $data['created_by'] = $request->user()->id;
        $data['mosque_id'] = $request->user()->isMosqueScoped()
            ? $request->user()->mosque_id
            : null;
        $data['status'] = Survey::STATUS_DRAFT;
        $data['logo_path'] = $this->saveFile($request->file('logo'), 'surveys/logos');

        $survey = Survey::create($data);

        return response()->json([
            'code' => 201,
            'message' => 'تم إنشاء مسودة الاستبيان بنجاح.',
            'data' => $this->definitionService->serialize($this->definitionService->load($survey)),
        ], 201);
    }

    public function show(Survey $survey): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => 'تم جلب تفاصيل الاستبيان بنجاح.',
            'data' => $this->definitionService->serialize($this->definitionService->load($survey)),
        ]);
    }

    public function update(UpdateSurveyRequest $request, Survey $survey): JsonResponse
    {
        $data = $request->safe()->except('logo');
        $oldLogo = $survey->logo_path;

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->saveFile($request->file('logo'), 'surveys/logos');
        }

        $survey->update($data);
        if (isset($data['logo_path']) && $oldLogo) {
            $this->deleteFile($oldLogo);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم تحديث بيانات الاستبيان بنجاح.',
            'data' => $this->definitionService->serialize($this->definitionService->load($survey->refresh())),
        ]);
    }

    public function destroy(Survey $survey): JsonResponse
    {
        $survey->delete();

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف الاستبيان بنجاح ويمكن استعادته من قاعدة البيانات عند الحاجة.',
            'data' => null,
        ]);
    }

    public function saveDefinition(
        SaveSurveyDefinitionRequest $request,
        Survey $survey,
    ): JsonResponse {
        $survey = $this->definitionService->save($survey, $request->validated(), $request->user());

        return response()->json([
            'code' => 200,
            'message' => 'تم حفظ بنية الاستبيان وشروطه بنجاح.',
            'data' => $this->definitionService->serialize($survey),
        ]);
    }

    public function publication(Request $request, Survey $survey): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Survey::STATUS_DRAFT,
                Survey::STATUS_PUBLISHED,
                Survey::STATUS_CLOSED,
            ])],
        ]);

        if ($validated['status'] === Survey::STATUS_PUBLISHED) {
            $errors = $this->definitionService->publicationErrors($survey);
            if ($errors) {
                return response()->json([
                    'code' => 422,
                    'message' => 'تعذر نشر الاستبيان قبل معالجة المشكلات التالية.',
                    'errors' => ['publication' => $errors],
                    'data' => null,
                ], 422);
            }
            $survey->published_at ??= now();
        }

        $survey->status = $validated['status'];
        $survey->save();

        $message = match ($survey->status) {
            Survey::STATUS_PUBLISHED => 'تم نشر الاستبيان وأصبح الرابط العام فعالًا.',
            Survey::STATUS_CLOSED => 'تم إغلاق الاستبيان وإيقاف استقبال الردود.',
            default => 'تم إلغاء نشر الاستبيان وإعادته إلى المسودة.',
        };

        return response()->json([
            'code' => 200,
            'message' => $message,
            'data' => $this->definitionService->serialize($this->definitionService->load($survey)),
        ]);
    }

    public function studentFields(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => 'تم جلب حقول الطالب المتاحة بنجاح.',
            'data' => $this->fieldRegistry->availableForUser($request->user()),
        ]);
    }

    public function responses(Request $request, Survey $survey): JsonResponse
    {
        $responses = $this->responseService->responses($survey, $request->only([
            'search',
            'from',
            'to',
            'sort_direction',
            'limit',
        ]), $request->user());

        return $this->paginated($responses, 'تم جلب ردود الاستبيان بنجاح.');
    }

    public function response(Request $request, Survey $survey, SurveyResponse $response): JsonResponse
    {
        abort_unless((int) $response->survey_id === (int) $survey->id, 404);
        $response->load(['answers.files', 'student:id,first_name,last_name']);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب تفاصيل الرد بنجاح.',
            'data' => $this->responseService->serializeResponse($response, $request->user()),
        ]);
    }

    public function downloadFile(string $accessToken)
    {
        $file = SurveyResponseFile::query()->where('access_token', $accessToken)->firstOrFail();
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream'],
        );
    }

    private function paginated($paginator, string $message): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
