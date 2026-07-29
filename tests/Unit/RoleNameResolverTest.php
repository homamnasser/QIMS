<?php

namespace Tests\Unit;

use App\Enums\RoleFamily;
use App\Support\RoleNameResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoleNameResolverTest extends TestCase
{
    #[DataProvider('roleNames')]
    public function test_it_resolves_flexible_role_names(string $name, RoleFamily $expected): void
    {
        $resolver = new RoleNameResolver;

        $this->assertSame($expected, $resolver->resolve($name));
    }

    /**
     * @return array<string, array{string, RoleFamily}>
     */
    public static function roleNames(): array
    {
        return [
            'canonical student' => ['student', RoleFamily::Student],
            'Arabic student' => ['طالب متقدم', RoleFamily::Student],
            'Arabic pupil' => ['تلميذ الحلقة', RoleFamily::Student],
            'Arabic supervisor' => ['مشرف المسجد', RoleFamily::Supervisor],
            'Arabic responsible' => ['مسؤول المشروع', RoleFamily::Supervisor],
            'Arabic manager' => ['مدير الدورة', RoleFamily::Supervisor],
            'canonical field supervisor' => ['field-supervisor', RoleFamily::FieldSupervisor],
            'Arabic field supervisor' => ['مشرف ميداني', RoleFamily::FieldSupervisor],
            'regional field supervisor' => ['regional field supervisor', RoleFamily::FieldSupervisor],
            'negated Arabic responsibility' => ['غير مسؤول', RoleFamily::Custom],
            'negated Arabic supervisor' => ['ليس مشرف الحلقة', RoleFamily::Custom],
            'negated English supervisor' => ['non-supervisor', RoleFamily::Custom],
            'substring is not a role token' => ['لامسؤولية إعلامية', RoleFamily::Custom],
            'custom English supervisor' => ['custom-supervisor', RoleFamily::Supervisor],
            'custom admin' => ['regional-admin', RoleFamily::Admin],
            'Arabic teacher' => ['مُعَلِّم الحلقة', RoleFamily::Teacher],
            'unclassified role' => ['منسق إعلامي', RoleFamily::Custom],
            'protected root role' => ['super-admin', RoleFamily::SuperAdmin],
            'root-like alias is not root' => ['assistant-super-admin', RoleFamily::Admin],
        ];
    }

    public function test_super_admin_is_not_an_assignable_custom_family(): void
    {
        $this->assertNotContains(RoleFamily::SuperAdmin->value, RoleFamily::assignableValues());
    }
}
