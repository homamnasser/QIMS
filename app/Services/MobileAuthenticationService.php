<?php

namespace App\Services;

use App\IService\IMobileAuthenticationService;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAuthenticationService implements IMobileAuthenticationService
{
    public function login(array $credentials): ?array
    {
        $account = $this->findAllowedAccount($credentials['login']);

        if (! $account || ! Hash::check($credentials['password'], $account->password)) {
            return null;
        }

        if (Hash::needsRehash($account->password)) {
            $account->forceFill([
                'password' => Hash::make($credentials['password']),
            ])->save();
        }

        return DB::transaction(fn (): array => [
            'account' => $account,
            'tokens' => $this->issueTokenPair($account, $credentials['device_name']),
        ]);
    }

    public function refresh(Authenticatable $account): ?array
    {
        $currentToken = $account->currentAccessToken();

        if (! $currentToken instanceof PersonalAccessToken) {
            return null;
        }

        return DB::transaction(function () use ($account, $currentToken): ?array {
            /** @var PersonalAccessToken|null $lockedToken */
            $lockedToken = $account->tokens()
                ->whereKey($currentToken->getKey())
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedToken
                || ($lockedToken->expires_at && $lockedToken->expires_at->isPast())
                || ! in_array(
                    config('auth_tokens.mobile.abilities.refresh'),
                    $lockedToken->abilities ?? [],
                    true
                )
            ) {
                return null;
            }

            $metadata = $this->parseTokenName($lockedToken->name);
            if (! $metadata || $metadata['kind'] !== 'refresh') {
                return null;
            }

            $this->revokePair($account, $metadata['pair_id']);

            return [
                'account' => $account,
                'tokens' => $this->issueTokenPair(
                    $account,
                    $metadata['device_name']
                ),
            ];
        });
    }

    public function logout(Authenticatable $account): bool
    {
        $currentToken = $account->currentAccessToken();

        if (! $currentToken instanceof PersonalAccessToken) {
            return false;
        }

        $metadata = $this->parseTokenName($currentToken->name);
        if ($metadata) {
            $this->revokePair($account, $metadata['pair_id']);
        } else {
            $currentToken->delete();
        }

        return true;
    }

    private function findAllowedAccount(string $login): User|Student|null
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::query()->where('email', $login)->first();
            $mobileStaffFamilies = config('roles.mobile_staff_families', []);

            return $user && $user->hasRoleFamily(...$mobileStaffFamilies)
                ? $user
                : null;
        }

        return Student::query()->where('username', $login)->first();
    }

    private function issueTokenPair(
        Authenticatable $account,
        string $deviceName
    ): array {
        $pairId = (string) Str::uuid();
        $safeDeviceName = $this->safeDeviceName($deviceName);
        $accessExpiresAt = now()->addMinutes(
            config('auth_tokens.mobile.access_token_minutes')
        );
        $refreshExpiresAt = now()->addDays(
            config('auth_tokens.mobile.refresh_token_days')
        );

        $accessToken = $account->createToken(
            $this->tokenName($pairId, 'access', $safeDeviceName),
            [config('auth_tokens.mobile.abilities.access')],
            $accessExpiresAt
        );
        $refreshToken = $account->createToken(
            $this->tokenName($pairId, 'refresh', $safeDeviceName),
            [config('auth_tokens.mobile.abilities.refresh')],
            $refreshExpiresAt
        );

        return [
            'token_type' => 'Bearer',
            'access_token' => $accessToken->plainTextToken,
            'access_token_expires_at' => $accessExpiresAt->toIso8601String(),
            'expires_in' => (int) now()->diffInSeconds($accessExpiresAt),
            'refresh_token' => $refreshToken->plainTextToken,
            'refresh_token_expires_at' => $refreshExpiresAt->toIso8601String(),
        ];
    }

    private function revokePair(
        Authenticatable $account,
        string $pairId
    ): void {
        $account->tokens()
            ->where('name', 'like', "mobile|{$pairId}|%")
            ->delete();
    }

    private function tokenName(
        string $pairId,
        string $kind,
        string $deviceName
    ): string {
        return "mobile|{$pairId}|{$kind}|{$deviceName}";
    }

    private function parseTokenName(string $name): ?array
    {
        $parts = explode('|', $name, 4);

        if (
            count($parts) !== 4
            || $parts[0] !== 'mobile'
            || ! Str::isUuid($parts[1])
            || ! in_array($parts[2], ['access', 'refresh'], true)
        ) {
            return null;
        }

        return [
            'pair_id' => $parts[1],
            'kind' => $parts[2],
            'device_name' => $parts[3],
        ];
    }

    private function safeDeviceName(string $deviceName): string
    {
        return Str::limit(
            str_replace('|', '-', trim($deviceName)),
            80,
            ''
        );
    }
}
