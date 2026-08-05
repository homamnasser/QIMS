<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات تقييم التحسن في القراءة.
 *
 * المعيار كان بلا مسار ولا صلاحية، فكان السجل الوحيد الذي يبقى «غير جاهز»
 * مهما فعل المستخدم من الواجهة. الهجرة تُنشئ الصلاحيات للقواعد القائمة؛
 * وإسنادها إلى الأدوار يبقى قرار إدارة من صفحة الأدوار.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'عرض كافة تقييمات القراءة',
        'إنشاء تقييم قراءة',
        'عرض تفاصيل تقييم القراءة',
        'تعديل تقييم القراءة',
        'حذف تقييم القراءة',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
