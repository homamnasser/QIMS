<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_uses_an_http_only_session_and_never_returns_an_api_token(): void
    {
        config([
            'session.cookie' => 'qims_web_session',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
        $user = $this->createUser('dashboard@example.com', '0991000001');

        $response = $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/web/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissing(['token'])
            ->assertJsonMissing(['access_token']);

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );

        $sessionCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'qims_web_session');

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertSame('lax', strtolower($sessionCookie->getSameSite()));
        $this->assertCount(0, $user->tokens()->get());
        $this->assertAuthenticatedAs($user, 'web');

        $this->getJson('/api/web/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_web_logout_invalidates_only_the_browser_session(): void
    {
        $user = $this->createUser('logout@example.com', '0991000002');
        $mobileToken = $user->createToken(
            'unrelated-mobile-token',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/web/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertOk();

        $this->postJson('/api/web/auth/logout')->assertOk();

        $this->assertGuest('web');
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $mobileToken->accessToken->id,
        ]);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/web/auth/me')->assertUnauthorized();
    }

    public function test_web_login_rejects_requests_outside_the_trusted_spa_channel(): void
    {
        $user = $this->createUser('untrusted@example.com', '0991000006');

        $this->postJson('/api/web/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'WEB_FRONTEND_REQUIRED');

        $this->withHeader('Origin', 'https://untrusted.example')
            ->postJson('/api/web/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'WEB_FRONTEND_REQUIRED');

        $this->assertGuest('web');
    }

    public function test_mobile_student_login_returns_expiring_device_tokens_without_cookies(): void
    {
        $student = $this->createStudent('mobile-student');

        $response = $this->postJson('/api/mobile/auth/login', [
            'login' => $student->username,
            'password' => 'student123',
            'device_name' => 'Pixel Test Device',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.account_type', 'student')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'access_token_expires_at',
                    'expires_in',
                    'refresh_token',
                    'refresh_token_expires_at',
                ],
            ]);

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );

        $this->assertEmpty($response->headers->getCookies());

        $accessToken = PersonalAccessToken::findToken(
            $response->json('data.access_token')
        );
        $refreshToken = PersonalAccessToken::findToken(
            $response->json('data.refresh_token')
        );

        $this->assertSame(
            [config('auth_tokens.mobile.abilities.access')],
            $accessToken->abilities
        );
        $this->assertSame(
            [config('auth_tokens.mobile.abilities.refresh')],
            $refreshToken->abilities
        );
        $this->assertNotNull($accessToken->expires_at);
        $this->assertNotNull($refreshToken->expires_at);
        $this->assertTrue($refreshToken->expires_at->isAfter($accessToken->expires_at));
    }

    public function test_mobile_login_allows_students_and_teachers_but_not_dashboard_admins(): void
    {
        $teacherRole = $this->createRole('mobile-teacher', RoleFamily::Teacher);
        $adminRole = $this->createRole('dashboard-admin', RoleFamily::Admin);
        $teacher = $this->createUser('teacher@example.com', '0991000003');
        $admin = $this->createUser('admin@example.com', '0991000004');
        $teacher->syncRoles([$teacherRole]);
        $admin->syncRoles([$adminRole]);

        $this->postJson('/api/mobile/auth/login', [
            'login' => $teacher->email,
            'password' => 'password123',
            'device_name' => 'Teacher Phone',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role_family', RoleFamily::Teacher->value);

        $this->postJson('/api/mobile/auth/login', [
            'login' => $admin->email,
            'password' => 'password123',
            'device_name' => 'Admin Phone',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'AUTHENTICATION_FAILED');
    }

    public function test_mobile_refresh_rotates_both_tokens_and_rejects_reuse(): void
    {
        $student = $this->createStudent('refresh-student');
        $login = $this->postJson('/api/mobile/auth/login', [
            'login' => $student->username,
            'password' => 'student123',
            'device_name' => 'Refresh Device',
        ])->assertOk();
        $oldAccessToken = $login->json('data.access_token');
        $oldRefreshToken = $login->json('data.refresh_token');

        $refresh = $this->withFreshBearer($oldRefreshToken)
            ->postJson('/api/mobile/auth/refresh')
            ->assertOk();

        $this->assertNotSame(
            $oldAccessToken,
            $refresh->json('data.access_token')
        );
        $this->assertNotSame(
            $oldRefreshToken,
            $refresh->json('data.refresh_token')
        );
        $this->assertNull(PersonalAccessToken::findToken($oldAccessToken));
        $this->assertNull(PersonalAccessToken::findToken($oldRefreshToken));
        $this->assertCount(2, $student->tokens()->get());

        $this->withFreshBearer($oldRefreshToken)
            ->postJson('/api/mobile/auth/refresh')
            ->assertUnauthorized();
        $this->withFreshBearer($oldAccessToken)
            ->getJson('/api/mobile/auth/me')
            ->assertUnauthorized();
    }

    public function test_mobile_logout_revokes_only_the_current_device_pair(): void
    {
        $student = $this->createStudent('logout-student');
        $firstDevice = $this->mobileLogin($student, 'First Device');
        $secondDevice = $this->mobileLogin($student, 'Second Device');

        $this->withFreshBearer($firstDevice['access_token'])
            ->postJson('/api/mobile/auth/logout')
            ->assertOk();

        $this->assertNull(
            PersonalAccessToken::findToken($firstDevice['access_token'])
        );
        $this->assertNull(
            PersonalAccessToken::findToken($firstDevice['refresh_token'])
        );
        $this->assertNotNull(
            PersonalAccessToken::findToken($secondDevice['access_token'])
        );
        $this->assertNotNull(
            PersonalAccessToken::findToken($secondDevice['refresh_token'])
        );
    }

    public function test_expired_and_wrong_purpose_mobile_tokens_are_rejected(): void
    {
        $student = $this->createStudent('expiry-student');
        $tokens = $this->mobileLogin($student, 'Expiry Device');
        $accessToken = PersonalAccessToken::findToken($tokens['access_token']);
        $accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withFreshBearer($tokens['access_token'])
            ->getJson('/api/mobile/auth/me')
            ->assertUnauthorized();
        $this->withFreshBearer($tokens['refresh_token'])
            ->getJson('/api/mobile/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'AUTH_CHANNEL_MISMATCH');
    }

    public function test_web_and_mobile_credentials_cannot_cross_authentication_channels(): void
    {
        $teacherRole = $this->createRole('channel-teacher', RoleFamily::Teacher);
        $permission = Permission::firstOrCreate([
            'name' => 'عرض كافة الموظفين',
            'guard_name' => 'web',
        ]);
        $teacher = $this->createUser('channel@example.com', '0991000005');
        $teacher->syncRoles([$teacherRole]);
        $teacher->givePermissionTo($permission);
        $tokens = $this->mobileLogin($teacher, 'Channel Device');

        $this->withFreshBearer($tokens['access_token'])
            ->getJson('/api/getAllStaff')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'AUTH_CHANNEL_MISMATCH');

        $legacyWildcardToken = $teacher->createToken(
            'legacy-wildcard-token',
            ['*']
        )->plainTextToken;
        $this->withFreshBearer($legacyWildcardToken)
            ->getJson('/api/mobile/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'AUTH_CHANNEL_MISMATCH');

        $this->withoutBearer()
            ->withCookie('qims_auth_token', $legacyWildcardToken)
            ->getJson('/api/getAllStaff')
            ->assertUnauthorized();
    }

    public function test_legacy_mixed_login_and_logout_routes_are_removed(): void
    {
        $this->postJson('/api/loginUser')->assertNotFound();
        $this->postJson('/api/logout')->assertNotFound();
    }

    public function test_cutover_migration_revokes_only_pre_separation_tokens(): void
    {
        $student = $this->createStudent('migration-student');
        $legacyToken = $student->createToken('API TOKEN', ['*']);
        $mobileToken = $student->createToken(
            'mobile|'.fake()->uuid().'|access|Migration Device',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );

        $migration = require database_path(
            'migrations/2026_07_28_000000_revoke_legacy_authentication_tokens.php'
        );
        $migration->up();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $legacyToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $mobileToken->accessToken->id,
        ]);
    }

    private function mobileLogin(
        Student|User $account,
        string $deviceName
    ): array {
        $this->withoutBearer();

        $login = $account instanceof Student ? $account->username : $account->email;

        return $this->postJson('/api/mobile/auth/login', [
            'login' => $login,
            'password' => $account instanceof Student
                ? 'student123'
                : 'password123',
            'device_name' => $deviceName,
        ])->assertOk()->json('data');
    }

    private function withFreshBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function withoutBearer(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', '');
    }

    private function createRole(string $name, RoleFamily $family): Role
    {
        return Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'role_family' => $family->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
    }

    private function createUser(string $email, string $phone): User
    {
        return User::create([
            'first_name' => 'مستخدم',
            'last_name' => 'اختبار',
            'email' => $email,
            'phone' => $phone,
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
    }

    private function createStudent(string $username): Student
    {
        return Student::create([
            'first_name' => 'طالب',
            'last_name' => 'اختبار',
            'username' => $username,
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '098'.str_pad(
                (string) Student::count(),
                7,
                '0',
                STR_PAD_LEFT
            ),
            'password' => 'student123',
        ]);
    }
}
