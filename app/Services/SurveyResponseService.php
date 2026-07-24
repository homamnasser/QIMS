<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyLogicRule;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurveyResponseService
{
    public function __construct(
        private readonly SurveyDefinitionService $definitionService,
        private readonly SurveyStudentFieldRegistry $fieldRegistry,
    ) {}

    public function publicSummary(Survey $survey): array
    {
        return [
            'name' => $survey->name,
            'description' => $survey->description,
            'logo_url' => $survey->logo_path ? asset('storage/'.ltrim($survey->logo_path, '/')) : null,
            'status' => $survey->status,
            'is_available' => $survey->isAvailable(),
            'starts_at' => $survey->starts_at?->toIso8601String(),
            'ends_at' => $survey->ends_at?->toIso8601String(),
        ];
    }

    public function identify(Survey $survey, string $selfnumber): array
    {
        $this->assertAvailable($survey);

        $student = Student::query()
            ->where('selfnumber', strtoupper(trim($selfnumber)))
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'selfnumber' => 'الرقم الذاتي غير موجود. تحقق من الرقم ثم حاول مجددًا.',
            ]);
        }

        if (! $survey->allow_multiple_responses
            && $survey->responses()->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages([
                'selfnumber' => 'سبق إرسال رد لهذا الاستبيان باستخدام هذا الرقم الذاتي.',
            ]);
        }

        $survey = $this->definitionService->load($survey);
        $allValues = $this->fieldRegistry->resolve($student);
        $studentFields = $survey->studentFields->map(function ($field) use ($allValues): array {
            $definition = $this->fieldRegistry->definition($field->field_key);

            return [
                'field_key' => $field->field_key,
                'label' => $field->label,
                'mode' => $field->mode,
                'is_required' => $field->is_required,
                'type' => $definition['type'] ?? 'text',
                'value' => $allValues[$field->field_key] ?? null,
            ];
        })->values()->all();

        $token = Crypt::encryptString(json_encode([
            'survey_id' => $survey->id,
            'student_id' => $student->id,
            'public_token' => $survey->public_token,
            'expires_at' => now()->addMinutes(
                max(5, (int) config('surveys.student_access_token_minutes', 30)),
            )->timestamp,
        ], JSON_THROW_ON_ERROR));

        $definition = $this->definitionService->serialize($survey);
        unset($definition['creator'], $definition['public_url'], $definition['preview_url'], $definition['responses_count']);
        $definition['student_fields'] = $studentFields;

        return [
            'access_token' => $token,
            'student' => [
                'selfnumber' => $student->selfnumber,
                'display_name' => trim($student->first_name.' '.$student->last_name),
            ],
            'survey' => $definition,
        ];
    }

    public function submit(Survey $survey, array $data, array $files): SurveyResponse
    {
        $tokenData = $this->decodeToken($data['access_token']);
        if ((int) ($tokenData['survey_id'] ?? 0) !== (int) $survey->id
            || ! hash_equals((string) $survey->public_token, (string) ($tokenData['public_token'] ?? ''))) {
            throw ValidationException::withMessages([
                'access_token' => 'جلسة الاستبيان غير صالحة. أعد إدخال الرقم الذاتي.',
            ]);
        }

        $survey = $this->definitionService->load($survey);

        return DB::transaction(function () use ($survey, $tokenData, $data, $files): SurveyResponse {
            $lockedSurvey = Survey::query()->lockForUpdate()->findOrFail($survey->id);
            $this->assertAvailable($lockedSurvey);

            $student = Student::query()->lockForUpdate()->find($tokenData['student_id']);
            if (! $student || ! $student->selfnumber) {
                throw ValidationException::withMessages([
                    'access_token' => 'تعذر التحقق من الطالب. أعد بدء الاستبيان.',
                ]);
            }

            if (! $lockedSurvey->allow_multiple_responses
                && SurveyResponse::query()
                    ->where('survey_id', $lockedSurvey->id)
                    ->where('student_id', $student->id)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'selfnumber' => 'سبق إرسال رد لهذا الاستبيان باستخدام هذا الرقم الذاتي.',
                ]);
            }

            $answers = $data['answers'] ?? [];
            $studentInput = $data['student_fields'] ?? [];
            [$visibleQuestions, $requiredQuestions] = $this->evaluateLogic($survey, $answers);
            $this->validateAnswers($survey, $answers, $files, $visibleQuestions, $requiredQuestions);

            $studentSnapshot = $this->buildStudentSnapshot($survey, $student, $studentInput);
            $surveySnapshot = $this->definitionService->serialize($survey);
            unset($surveySnapshot['creator'], $surveySnapshot['public_url'], $surveySnapshot['preview_url']);

            $response = SurveyResponse::create([
                'survey_id' => $survey->id,
                'student_id' => $student->id,
                'student_selfnumber_snapshot' => $student->selfnumber,
                'survey_snapshot' => $surveySnapshot,
                'student_data_snapshot' => $studentSnapshot,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($survey->questions as $question) {
                if (! ($visibleQuestions[$question->id] ?? true)) {
                    continue;
                }

                $value = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
                $uploadedFile = $files[(string) $question->id] ?? $files[$question->id] ?? null;
                if ($this->isEmpty($value) && ! $uploadedFile) {
                    continue;
                }

                $answer = SurveyAnswer::create([
                    'response_id' => $response->id,
                    'question_id' => $question->id,
                    'question_key' => $question->question_key,
                    'question_title' => $question->title,
                    'question_type' => $question->type,
                    'value' => $uploadedFile ? null : $value,
                ]);

                if ($uploadedFile instanceof UploadedFile) {
                    $extension = $uploadedFile->getClientOriginalExtension();
                    $name = Str::uuid().($extension ? '.'.$extension : '');
                    $path = $uploadedFile->storeAs(
                        "survey-responses/{$survey->id}/{$response->id}",
                        $name,
                        'local',
                    );
                    $answer->files()->create([
                        'response_id' => $response->id,
                        'access_token' => Str::random(48),
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                    ]);
                }
            }

            return $response->load('answers.files');
        }, 3);
    }

    public function responses(Survey $survey, array $filters, User $user)
    {
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 20)));
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $survey->responses()
            ->with(['answers.files', 'student:id,first_name,last_name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('student_selfnumber_snapshot', 'like', '%'.$search.'%')
                        ->orWhereHas('student', function ($studentQuery) use ($search): void {
                            $studentQuery->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('submitted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('submitted_at', '<=', $to))
            ->orderBy('submitted_at', $sortDirection);

        return $query->paginate($limit)->through(
            fn (SurveyResponse $response): array => $this->serializeResponse($response, $user),
        );
    }

    public function serializeResponse(SurveyResponse $response, User $user): array
    {
        $canViewStudentDetails = $user->can('عرض تفاصيل الطالب');
        $studentData = collect($response->student_data_snapshot ?? [])
            ->reject(function (array $field) use ($canViewStudentDetails): bool {
                $definition = $this->fieldRegistry->definition($field['field_key'] ?? '');

                return ! $canViewStudentDetails && ($definition['sensitive'] ?? false);
            })
            ->values()
            ->all();

        return [
            'id' => $response->id,
            'response_token' => $response->response_token,
            'selfnumber' => $response->student_selfnumber_snapshot,
            'student_name' => $response->student
                ? trim($response->student->first_name.' '.$response->student->last_name)
                : collect($studentData)->firstWhere('field_key', 'basic.full_name')['value'] ?? 'طالب محذوف',
            'student_data' => $studentData,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
            'answers' => $response->answers->map(fn (SurveyAnswer $answer): array => [
                'question_key' => $answer->question_key,
                'question_title' => $answer->question_title,
                'question_type' => $answer->question_type,
                'value' => $answer->value,
                'files' => $answer->files->map(fn ($file): array => [
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                    'download_url' => url('/api/surveys/files/'.$file->access_token),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function buildStudentSnapshot(Survey $survey, Student $student, array $studentInput): array
    {
        $values = $this->fieldRegistry->resolve($student);
        $snapshot = [];
        $errors = [];

        foreach ($survey->studentFields as $field) {
            $value = $values[$field->field_key] ?? null;
            if ($field->mode === 'editable' && array_key_exists($field->field_key, $studentInput)) {
                $value = $studentInput[$field->field_key];
            }

            if ($field->is_required && $this->isEmpty($value)) {
                $errors["student_fields.{$field->field_key}"] = "الحقل «{$field->label}» مطلوب.";
            }

            $snapshot[] = [
                'field_key' => $field->field_key,
                'label' => $field->label,
                'mode' => $field->mode,
                'value' => $value,
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $snapshot;
    }

    private function evaluateLogic(Survey $survey, array $answers): array
    {
        $questionVisibility = $survey->questions->mapWithKeys(fn ($question) => [$question->id => true])->all();
        $sectionVisibility = $survey->sections->mapWithKeys(fn ($section) => [$section->id => true])->all();
        $required = $survey->questions->mapWithKeys(fn ($question) => [$question->id => $question->is_required])->all();
        $rulesByTarget = $survey->logicRules->groupBy(
            fn (SurveyLogicRule $rule): string => $rule->target_type.':'
                .($rule->target_type === 'question' ? $rule->target_question_id : $rule->target_section_id),
        );

        $maxIterations = max(1, $survey->questions->count() + $survey->sections->count());
        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $nextQuestionVisibility = $survey->questions
                ->mapWithKeys(fn ($question) => [$question->id => true])
                ->all();
            $nextSectionVisibility = $survey->sections
                ->mapWithKeys(fn ($section) => [$section->id => true])
                ->all();

            foreach ($rulesByTarget as $targetKey => $rules) {
                [$targetType, $targetId] = explode(':', $targetKey);
                $matches = fn (SurveyLogicRule $rule): bool => ($questionVisibility[$rule->source_question_id] ?? true)
                    && $this->ruleMatches($rule, $answers);
                $showRules = $rules->where('action', 'show_if');
                $visible = $showRules->isEmpty() || $showRules->contains($matches);
                if ($rules->where('action', 'hide_if')->contains($matches)) {
                    $visible = false;
                }

                if ($targetType === 'question') {
                    $nextQuestionVisibility[(int) $targetId] = $visible;
                } else {
                    $nextSectionVisibility[(int) $targetId] = $visible;
                }
            }

            foreach ($survey->sections as $section) {
                if (! ($nextSectionVisibility[$section->id] ?? true)) {
                    foreach ($section->questions as $question) {
                        $nextQuestionVisibility[$question->id] = false;
                    }
                }
            }

            if ($nextQuestionVisibility === $questionVisibility && $nextSectionVisibility === $sectionVisibility) {
                break;
            }
            $questionVisibility = $nextQuestionVisibility;
            $sectionVisibility = $nextSectionVisibility;
        }

        foreach ($survey->logicRules->where('action', 'require_if')->where('target_type', 'question') as $rule) {
            if (($questionVisibility[$rule->target_question_id] ?? true)
                && ($questionVisibility[$rule->source_question_id] ?? true)
                && $this->ruleMatches($rule, $answers)) {
                $required[$rule->target_question_id] = true;
            }
        }

        foreach ($survey->logicRules->where('action', 'require_if')->where('target_type', 'section') as $rule) {
            if (($sectionVisibility[$rule->target_section_id] ?? true)
                && ($questionVisibility[$rule->source_question_id] ?? true)
                && $this->ruleMatches($rule, $answers)) {
                foreach ($survey->sections->firstWhere('id', $rule->target_section_id)?->questions ?? [] as $question) {
                    $required[$question->id] = true;
                }
            }
        }

        return [$questionVisibility, $required];
    }

    private function ruleMatches(SurveyLogicRule $rule, array $answers): bool
    {
        $answer = $answers[(string) $rule->source_question_id] ?? $answers[$rule->source_question_id] ?? null;
        $answerValues = is_array($answer) ? array_map('strval', $answer) : [(string) $answer];
        $ruleValues = array_map('strval', $rule->values ?? []);

        return match ($rule->operator) {
            'equals' => count($answerValues) === 1 && in_array($answerValues[0], $ruleValues, true),
            'in', 'contains' => count(array_intersect($answerValues, $ruleValues)) > 0,
            default => false,
        };
    }

    private function validateAnswers(
        Survey $survey,
        array $answers,
        array $files,
        array $visible,
        array $required,
    ): void {
        $errors = [];

        foreach ($survey->questions as $question) {
            if (! ($visible[$question->id] ?? true)) {
                continue;
            }

            $value = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;
            $file = $files[(string) $question->id] ?? $files[$question->id] ?? null;
            if (($required[$question->id] ?? false) && $this->isEmpty($value) && ! $file) {
                $errors["answers.{$question->id}"] = "السؤال «{$question->title}» مطلوب.";

                continue;
            }
            if ($this->isEmpty($value) && ! $file) {
                continue;
            }

            if ($question->type === 'file' && ! $file instanceof UploadedFile) {
                $errors["files.{$question->id}"] = "أرفق ملفًا صالحًا للسؤال «{$question->title}».";
            }
            if ($question->type === 'radio'
                && ! $question->options->pluck('value')->contains((string) $value)) {
                $errors["answers.{$question->id}"] = "اختر قيمة صالحة للسؤال «{$question->title}».";
            }
            if ($question->type === 'checkbox') {
                if (! is_array($value)
                    || collect($value)->map('strval')->diff($question->options->pluck('value'))->isNotEmpty()) {
                    $errors["answers.{$question->id}"] = "توجد قيمة غير صالحة في السؤال «{$question->title}».";
                }
            }
            if (in_array($question->type, ['short_text', 'paragraph'], true) && ! is_string($value)) {
                $errors["answers.{$question->id}"] = "إجابة السؤال «{$question->title}» يجب أن تكون نصًا.";
            }
            if ($question->type === 'date' && ! $this->validDate((string) $value)) {
                $errors["answers.{$question->id}"] = "أدخل تاريخًا صالحًا للسؤال «{$question->title}».";
            }
            if ($question->type === 'time' && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $value)) {
                $errors["answers.{$question->id}"] = "أدخل وقتًا صالحًا للسؤال «{$question->title}».";
            }

            if (is_string($value)) {
                $length = mb_strlen($value);
                $min = data_get($question->validation_rules, 'min_length');
                $max = data_get($question->validation_rules, 'max_length');
                if ($min !== null && $length < (int) $min) {
                    $errors["answers.{$question->id}"] = "إجابة «{$question->title}» أقصر من الحد الأدنى.";
                }
                if ($max !== null && $length > (int) $max) {
                    $errors["answers.{$question->id}"] = "إجابة «{$question->title}» تتجاوز الحد الأقصى.";
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function decodeToken(string $token): array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages([
                'access_token' => 'جلسة الاستبيان غير صالحة. أعد إدخال الرقم الذاتي.',
            ]);
        }

        if (($data['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages([
                'access_token' => 'انتهت جلسة تعبئة الاستبيان. أعد إدخال الرقم الذاتي.',
            ]);
        }

        return $data;
    }

    private function assertAvailable(Survey $survey): void
    {
        if ($survey->status !== Survey::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'survey' => 'الاستبيان غير منشور حاليًا.',
            ]);
        }
        if ($survey->starts_at && $survey->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'survey' => 'لم يبدأ وقت استقبال الردود بعد.',
            ]);
        }
        if ($survey->ends_at && $survey->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'survey' => 'انتهى وقت استقبال الردود لهذا الاستبيان.',
            ]);
        }
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
