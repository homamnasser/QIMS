<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * الرسالة العربية الافتراضية لكل رمز حالة HTTP يستخدمه النظام.
     *
     * @var array<int, array{0: string, 1: string}> [error_code, message]
     */
    private const STATUS_MESSAGES = [
        400 => ['BAD_REQUEST', 'الطلب غير صالح.'],
        401 => ['UNAUTHENTICATED', 'غير مصادق — يجب تسجيل الدخول أولاً.'],
        403 => ['FORBIDDEN', 'ممنوع: لا تملك الصلاحيات المطلوبة لتنفيذ هذا الإجراء.'],
        404 => ['NOT_FOUND', 'العنصر المطلوب غير موجود أو غير متاح لك.'],
        405 => ['METHOD_NOT_ALLOWED', 'طريقة الطلب غير مدعومة لهذا المسار.'],
        409 => ['CONFLICT', 'تعارض مع الحالة الحالية للسجل؛ يرجى تحديث الصفحة وإعادة المحاولة.'],
        410 => ['GONE', 'العنصر المطلوب لم يعد ساريًا.'],
        413 => ['PAYLOAD_TOO_LARGE', 'حجم البيانات المرسلة أكبر من المسموح.'],
        419 => ['SESSION_EXPIRED', 'انتهت صلاحية الجلسة؛ يرجى تحديث الصفحة وإعادة المحاولة.'],
        422 => ['VALIDATION_FAILED', 'تعذر حفظ البيانات؛ يرجى مراجعة الحقول المميزة.'],
        429 => ['TOO_MANY_REQUESTS', 'عدد المحاولات كبير؛ يرجى الانتظار قليلاً ثم إعادة المحاولة.'],
        500 => ['SERVER_ERROR', 'حدث خطأ غير متوقع في الخادم. تم تسجيل التفاصيل الفنية وسيراجعها الفريق التقني.'],
        503 => ['SERVICE_UNAVAILABLE', 'الخدمة متوقفة مؤقتًا للصيانة؛ يرجى المحاولة لاحقًا.'],
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (UnauthorizedException $e, $request) {
            return $this->apiError($request, 403, 'FORBIDDEN', 'ممنوع: لا تملك الصلاحيات المطلوبة للوصول.');
        });

        $this->renderable(function (ProjectHasCoursesException $e, $request) {
            return $this->apiError(
                $request,
                409,
                'PROJECT_HAS_COURSES',
                'لا يمكن حذف المشروع لأنه مرتبط بكورس واحد أو أكثر. يمكنك أرشفة المشروع بدلًا من حذفه، أو نقل الكورسات المرتبطة أو حذفها أولًا.',
                ['courses_count' => $e->coursesCount],
            );
        });

        $this->renderable(function (MosqueHasCoursesException $e, $request) {
            return $this->apiError(
                $request,
                409,
                'MOSQUE_HAS_COURSES',
                'لا يمكن حذف المسجد لأنه مرتبط بكورس واحد أو أكثر. يرجى نقل الكورسات المرتبطة بالمسجد أو حذفها أولاً.',
                ['courses_count' => $e->coursesCount],
            );
        });

        $this->renderable(function (MosqueHasScopedRecordsException $e, $request) {
            return $this->apiError(
                $request,
                409,
                'MOSQUE_HAS_SCOPED_RECORDS',
                'لا يمكن حذف المسجد لأنه مرتبط بموظفين مقيّدين أو طلاب أو استبيانات. انقل هذه السجلات إلى نطاق آخر قبل حذف المسجد.',
                [
                    'staff_count' => $e->staffCount,
                    'students_count' => $e->studentsCount,
                    'surveys_count' => $e->surveysCount,
                ],
            );
        });

        // المعالج الشامل: يجب أن يبقى آخر معالج مسجّل. يضمن أن كل استجابة خطأ
        // على مسارات الـ API موحدة الشكل وبرسالة عربية، وألا يصل إلى الواجهة
        // أي نص SQL أو أثر استدعاء أو رسالة داخلية طويلة.
        $this->renderable(fn (Throwable $e, $request) => $this->renderApiException($e, $request));
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if (! $this->isApiRequest($request)) {
            return parent::unauthenticated($request, $exception);
        }

        return $this->apiError($request, 401, 'UNAUTHENTICATED', 'غير مصادق — يجب تسجيل الدخول أولاً.');
    }

    /**
     * يحوّل أي استثناء غير معالج إلى استجابة JSON موحدة على مسارات الـ API.
     */
    private function renderApiException(Throwable $e, $request): ?JsonResponse
    {
        // استثناء يحمل استجابة جاهزة (مثل abort_if داخل middleware) يمر كما هو.
        if ($e instanceof HttpResponseException || ! $this->isApiRequest($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->apiError(
                $request,
                $e->status,
                'VALIDATION_FAILED',
                $this->firstValidationMessage($e),
                null,
                $e->errors(),
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->apiError($request, 401, 'UNAUTHENTICATED', 'غير مصادق — يجب تسجيل الدخول أولاً.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            [$code, $message] = self::STATUS_MESSAGES[$status] ?? self::STATUS_MESSAGES[500];

            // رسالة abort() العربية المكتوبة عمدًا تُعرض كما هي؛ أما الرسائل
            // الإنجليزية الافتراضية للإطار (وبعضها يكشف أسماء الموديلات) فتُستبدل.
            return $this->apiError($request, $status, $code, $this->preferArabic($e->getMessage(), $message));
        }

        // خطأ قاعدة بيانات أو خطأ برمجي غير متوقع: يسجَّل كاملاً ولا يخرج منه شيء.
        $isDatabaseFailure = $e instanceof QueryException;
        $this->reportUnexpected($e, $request, $isDatabaseFailure);

        return $this->apiError(
            $request,
            500,
            $isDatabaseFailure ? 'DATABASE_ERROR' : 'SERVER_ERROR',
            $isDatabaseFailure
                ? 'تعذر إتمام العملية بسبب خطأ في قاعدة البيانات. لم يُحفظ أي تعديل، وسُجلت التفاصيل الفنية للفريق التقني.'
                : self::STATUS_MESSAGES[500][1],
            null,
            null,
            $e,
        );
    }

    /**
     * يبني الاستجابة الموحدة لأي خطأ.
     *
     * @param  array<string, mixed>|null  $data
     * @param  array<string, array<int, string>>|null  $errors
     */
    private function apiError(
        $request,
        int $status,
        string $errorCode,
        string $message,
        ?array $data = null,
        ?array $errors = null,
        ?Throwable $exception = null,
    ): ?JsonResponse {
        if (! $this->isApiRequest($request)) {
            return null;
        }

        $payload = [
            'code' => $status,
            'error_code' => $errorCode,
            'message' => $message,
            'data' => $data,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        // تفاصيل المطور تظهر في مفتاح منفصل وفي بيئة التطوير فقط، ولا تدخل
        // أبدًا في حقل message الذي تعرضه الواجهة للمستخدم.
        if ($exception && config('app.debug')) {
            $payload['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ];
        }

        return response()->json($payload, $status);
    }

    /**
     * يسجل التفاصيل الفنية الكاملة للأخطاء غير المتوقعة في سجل الخادم.
     */
    private function reportUnexpected(Throwable $e, $request, bool $isDatabaseFailure): void
    {
        $context = [
            'error_code' => $isDatabaseFailure ? 'DATABASE_ERROR' : 'SERVER_ERROR',
            'exception' => $e::class,
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => optional($request->route())->getName(),
            'user_id' => optional($request->user())->getAuthIdentifier(),
        ];

        if ($e instanceof QueryException) {
            $context['sql'] = $e->getSql();
            $context['sql_error_code'] = $e->getCode();
        }

        // الإطار يسجل الاستثناء وأثره الكامل تلقائيًا؛ هذا السطر يضيف سياق
        // الطلب والمستخدم وجملة SQL إلى السجل نفسه لتسهيل التشخيص.
        logger()->error($e->getMessage(), $context);
    }

    /**
     * أول رسالة تحقق مفهومة، لأن الواجهة تعرضها مباشرة في التنبيه.
     */
    private function firstValidationMessage(ValidationException $e): string
    {
        $first = collect($e->errors())->flatten()->first();

        return is_string($first) && $first !== ''
            ? $first
            : self::STATUS_MESSAGES[422][1];
    }

    /**
     * يُبقي الرسالة المكتوبة بالعربية ويستبدل غيرها بالرسالة العربية الافتراضية.
     */
    private function preferArabic(?string $thrown, string $fallback): string
    {
        $thrown = trim((string) $thrown);

        return $thrown !== '' && preg_match('/\p{Arabic}/u', $thrown) === 1
            ? $thrown
            : $fallback;
    }

    private function isApiRequest($request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
