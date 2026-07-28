<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebAuthenticationController extends Controller
{
    public function login(WebLoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            return $this->noStore(response()->json([
                'code' => 401,
                'error_code' => 'AUTHENTICATION_FAILED',
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
                'data' => null,
            ], 401));
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'تم تسجيل الدخول إلى لوحة التحكم بنجاح.',
            'data' => [
                'user' => new UserResource($user),
                'session_expires_at' => now()
                    ->addMinutes((int) config('session.lifetime'))
                    ->toIso8601String(),
            ],
        ]));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'جلسة لوحة التحكم فعالة.',
            'data' => [
                'user' => new UserResource($user),
                'session_expires_at' => now()
                    ->addMinutes((int) config('session.lifetime'))
                    ->toIso8601String(),
            ],
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'تم تسجيل الخروج من لوحة التحكم بنجاح.',
            'data' => null,
        ]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private'
        );
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
