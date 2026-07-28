<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureFrontendRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            return new JsonResponse([
                'code' => 403,
                'error_code' => 'WEB_FRONTEND_REQUIRED',
                'message' => 'نقطة الدخول هذه مخصصة لتطبيق الويب الموثوق.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
