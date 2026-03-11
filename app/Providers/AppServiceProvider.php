<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\StaffService;
use App\IService\IStaffService;
use App\IService\IRoleService;
use App\Services\RoleService;
use App\Repositories\Interfaces\IRoleService as InterfacesIRoleService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind( IStaffService::class, StaffService::class);
        $this->app->bind(IRoleService::class, RoleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
