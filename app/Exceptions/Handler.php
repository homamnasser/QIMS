<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Spatie\Permission\Exceptions\UnauthorizedException;

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
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (UnauthorizedException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 403,
                    'message' => 'ممنوع: لا تملك الصلاحيات المطلوبة للوصول.',
                    'data' => null,
                ], 403);
            }
        });

        $this->renderable(function (ProjectHasCoursesException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 409,
                    'error_code' => 'PROJECT_HAS_COURSES',
                    'message' => 'لا يمكن حذف المشروع لأنه مرتبط بكورس واحد أو أكثر. يمكنك أرشفة المشروع بدلًا من حذفه، أو نقل الكورسات المرتبطة أو حذفها أولًا.',
                    'data' => [
                        'courses_count' => $e->coursesCount,
                    ],
                ], 409);
            }
        });

        $this->renderable(function (MosqueHasCoursesException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 409,
                    'error_code' => 'MOSQUE_HAS_COURSES',
                    'message' => 'لا يمكن حذف المسجد لأنه مرتبط بكورس واحد أو أكثر. يرجى نقل الكورسات المرتبطة بالمسجد أو حذفها أولاً.',
                    'data' => [
                        'courses_count' => $e->coursesCount,
                    ],
                ], 409);
            }
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'code' => 401,
            'message' => 'غير مصادق — يجب تسجيل الدخول أولاً.',
            'data' => null,
        ], 401);
    }
}
