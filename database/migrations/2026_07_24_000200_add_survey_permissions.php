<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'إنشاء استبيان',
        'تعديل استبيان',
        'حذف استبيان',
        'عرض تفاصيل الاستبيان',
        'عرض كافة الاستبيانات',
        'نشر وإلغاء نشر الاستبيان',
        'عرض وتصدير ردود الاستبيان',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
