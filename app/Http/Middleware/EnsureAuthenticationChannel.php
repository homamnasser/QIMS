<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class EnsureAuthenticationChannel
{
    public function handle(
        Request $request,
        Closure $next,
        string $channel
    ): mixed {
        $account = $request->user();
        $token = $account?->currentAccessToken();

        $allowed = match ($channel) {
            'web' => $account instanceof User
                && $token instanceof TransientToken,
            'mobile-access' => $this->isAllowedMobileAccount($account)
                && $this->hasExactAbility($token, 'access'),
            'mobile-student' => $account instanceof Student
                && $this->hasExactAbility($token, 'access'),
            'mobile-staff' => $this->isAllowedMobileStaff($account)
                && $this->hasExactAbility($token, 'access'),
            'mobile-refresh' => $this->isAllowedMobileAccount($account)
                && $this->hasExactAbility($token, 'refresh'),
            'mobile-token' => $this->isAllowedMobileAccount($account)
                && (
                    $this->hasExactAbility($token, 'access')
                    || $this->hasExactAbility($token, 'refresh')
                ),
            default => false,
        };

        if (! $allowed) {
            return new JsonResponse([
                'code' => 403,
                'error_code' => 'AUTH_CHANNEL_MISMATCH',
                'message' => $this->mismatchMessage($channel, $account),
                'data' => null,
            ], 403);
        }

        return $next($request);
    }

    private function isAllowedMobileAccount(mixed $account): bool
    {
        return $account instanceof Student
            || $this->isAllowedMobileStaff($account);
    }

    private function isAllowedMobileStaff(mixed $account): bool
    {
        return $account instanceof User
            && $account->hasRoleFamily(
                ...config('roles.mobile_staff_families', [])
            );
    }

    private function hasExactAbility(
        mixed $token,
        string $abilityKey
    ): bool {
        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return in_array(
            config("auth_tokens.mobile.abilities.{$abilityKey}"),
            $token->abilities ?? [],
            true
        );
    }

    private function mismatchMessage(string $channel, mixed $account): string
    {
        if ($channel === 'mobile-student' && ! $account instanceof Student) {
            return 'هذه الواجهات متاحة لحسابات الطلاب فقط.';
        }

        if ($channel === 'mobile-staff' && ! $this->isAllowedMobileStaff($account)) {
            return 'هذه الواجهات متاحة لحسابات الكادر الميداني المصرح له فقط.';
        }

        return 'رمز المصادقة لا يخص قناة الوصول المطلوبة.';
    }
}
