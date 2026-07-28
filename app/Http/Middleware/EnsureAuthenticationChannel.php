<?php

namespace App\Http\Middleware;

use App\Enums\RoleFamily;
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
                'message' => 'رمز المصادقة لا يخص قناة الوصول المطلوبة.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }

    private function isAllowedMobileAccount(mixed $account): bool
    {
        return $account instanceof Student
            || (
                $account instanceof User
                && $account->hasRoleFamily(RoleFamily::Teacher)
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
}
