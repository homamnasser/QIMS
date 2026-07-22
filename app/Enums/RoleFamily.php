<?php

namespace App\Enums;

enum RoleFamily: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Teacher = 'teacher';
    case Student = 'student';
    case Custom = 'custom';

    /**
     * Families that may be selected for a user-created role.
     *
     * The super-admin family is deliberately excluded: root access remains
     * exclusive to the protected canonical super-admin role.
     *
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return [
            self::Admin->value,
            self::Supervisor->value,
            self::Teacher->value,
            self::Student->value,
            self::Custom->value,
        ];
    }

    public function isSupervisory(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::Supervisor], true);
    }
}
