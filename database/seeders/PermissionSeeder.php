<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'login',
            'logout',
            'createStaffMember',
            'updateStaffMember',
            'getAllRoles',
            'createRole',
            'getRole',
            'updateRole',
            'deleteRole',
            'getAllProjects',
            'createProject',
            'getProject',
            'updateProject',
            'deleteProject',
            'getAllPermissions',
            'editProjectStatus',
            "getAllMosques",
            "createMosque",
            "updateMosque",
            "deleteMosque",
            "getMosque",
            "getAllStaff",
            "getStaffById",
            "deleteStaff",
            "getAllCourses",
            "createCourse",
            "getCourse",
            "updateCourse",
            "deleteCourse",
            


        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
