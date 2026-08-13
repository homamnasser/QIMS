<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnforceStaffMosqueScope;
use App\Http\Middleware\EnsureAuthenticationChannel;
use App\Http\Middleware\EnsureFrontendRequest;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // خلف موازن تحميل أو بروكسي، الترويسات المعتمدة تشمل ترويسة AWS ELB.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // اسم المستخدم وكلمات المرور تُترك كما أُرسلت: القص قد يغيّر قيمة
        // اعتماد صالحة أو يخفي خطأ إدخال يجب أن يظهر في التحقق.
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
            'username',
        ]);

        // مجموعة api: جلسات Sanctum للواجهة + محدّد المعدل 'api'.
        $middleware->statefulApi();
        $middleware->throttleApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'frontend.request' => EnsureFrontendRequest::class,
            'auth.channel' => EnsureAuthenticationChannel::class,
            'staff.mosque.scope' => EnforceStaffMosqueScope::class,
        ]);

        // ترتيب حرج: يجب التحقق من الهوية، ثم قناة المصادقة، ثم تفعيل نطاق
        // المسجد للموظف — قبل تحديد المعدل وربط الموديلات والتفويض.
        $middleware->appendToPriorityList(
            after: AuthenticatesRequests::class,
            append: EnsureAuthenticationChannel::class,
        );

        $middleware->appendToPriorityList(
            after: EnsureAuthenticationChannel::class,
            append: EnforceStaffMosqueScope::class,
        );

        // طلبات الـ API لا تُحوَّل إلى صفحة تسجيل دخول، بل تُرجع 401 كـ JSON.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*')
                ? null
                : route('login')
        );

        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        ApiExceptionHandler::register($exceptions);
    })->create();
