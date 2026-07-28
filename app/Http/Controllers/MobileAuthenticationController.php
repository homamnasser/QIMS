<?php

namespace App\Http\Controllers;

use App\Http\Requests\MobileLoginRequest;
use App\Http\Resources\StudentResource;
use App\Http\Resources\UserResource;
use App\IService\IMobileAuthenticationService;
use App\Models\Student;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileAuthenticationController extends Controller
{
    public function __construct(
        private readonly IMobileAuthenticationService $authenticationService
    ) {}

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $result = $this->authenticationService->login($request->validated());

        if (! $result) {
            return $this->noStore(response()->json([
                'code' => 401,
                'error_code' => 'AUTHENTICATION_FAILED',
                'message' => 'بيانات الدخول غير صحيحة أو الحساب غير متاح للموبايل.',
                'data' => null,
            ], 401));
        }

        return $this->tokenResponse(
            'تم تسجيل الدخول إلى تطبيق الموبايل بنجاح.',
            $result
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Authenticatable $account */
        $account = $request->user();

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'رمز دخول الموبايل فعال.',
            'data' => [
                'user' => $this->accountResource($account),
            ],
        ]));
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var Authenticatable $account */
        $account = $request->user();
        $result = $this->authenticationService->refresh($account);

        if (! $result) {
            return $this->noStore(response()->json([
                'code' => 401,
                'error_code' => 'INVALID_REFRESH_TOKEN',
                'message' => 'رمز التجديد غير صالح أو منتهي.',
                'data' => null,
            ], 401));
        }

        return $this->tokenResponse(
            'تم تدوير رموز الموبايل بنجاح.',
            $result
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Authenticatable $account */
        $account = $request->user();
        $this->authenticationService->logout($account);

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => 'تم إلغاء جلسة هذا الجهاز بنجاح.',
            'data' => null,
        ]));
    }

    private function tokenResponse(string $message, array $result): JsonResponse
    {
        /** @var Authenticatable $account */
        $account = $result['account'];

        return $this->noStore(response()->json([
            'code' => 200,
            'message' => $message,
            'data' => [
                'user' => $this->accountResource($account),
                ...$result['tokens'],
            ],
        ]));
    }

    private function accountResource(
        Authenticatable $account
    ): JsonResource {
        return $account instanceof Student
            ? new StudentResource($account)
            : new UserResource($account);
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
