<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = ['workflows.view.all', 'workflows.view.own', 'workflows.create', 'workflows.update', 'workflows.delete', 'users.manage', 'departments.manage', 'requests.submit', 'requests.view.own', 'requests.view.all', 'approvals.decide', 'monitoring.view', 'reports.view', 'notifications.view'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'Super Admin' => $permissions,
            'Workflow Designer' => ['workflows.view.own', 'workflows.create', 'workflows.update', 'workflows.delete', 'requests.view.own', 'notifications.view'],
            'Requester' => ['requests.submit', 'requests.view.own', 'notifications.view'],
            'Approver' => ['requests.view.own', 'approvals.decide', 'notifications.view'],
            'Auditor' => ['workflows.view.all', 'requests.view.all', 'monitoring.view', 'reports.view', 'notifications.view'],
        ];
        foreach ($roles as $name => $grants) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
