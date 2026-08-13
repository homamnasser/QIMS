<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $account = $request->user();
            $identity = $account
                ? $account::class.':'.$account->getAuthIdentifier()
                : $request->ip();

            return Limit::perMinute(180)->by($identity);
        });

        RateLimiter::for('web-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('login')).'|'.$request->ip()
            );
        });

        // تأكيد الهوية قبل تحميل الشهادة يفحص كلمة مرور، فهو سطح تخمين.
        // الحساب معروف مسبقاً (الطلب مصادق عليه) فنحدّ به لا بالـ IP وحده،
        // كي لا تُعطّل شبكة مشتركة طلاب مسجد كامل.
        RateLimiter::for('certificate-confirm', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->user()
                    ? $request->user()::class.':'.$request->user()->getAuthIdentifier()
                    : $request->ip()
            );
        });

        RateLimiter::for('mobile-refresh', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()
                    ? $request->user()::class.':'.$request->user()->getAuthIdentifier()
                    : $request->ip()
            );
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
