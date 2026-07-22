<?php

use App\Enums\RoleFamily;

return [
    'protected_names' => [
        'super-admin',
        'admin',
        'supervisor',
        'teacher',
        'student',
    ],

    'capabilities' => [
        'supervise' => 'الإشراف على المشاريع والكورسات',
    ],

    // These permissions are always added when a role is saved with the
    // corresponding family. All other permissions remain fully configurable.
    'family_permissions' => [
        RoleFamily::Admin->value => ['الإشراف على المشاريع والكورسات'],
        RoleFamily::Supervisor->value => ['الإشراف على المشاريع والكورسات'],
    ],
];
