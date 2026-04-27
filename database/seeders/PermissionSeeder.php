<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = config('authz.role_permissions', []);

        $permissionNames = collect($rolePermissions)
            ->flatten()
            ->filter(fn ($name) => is_string($name) && $name !== '*' && $name !== '')
            ->unique()
            ->values();

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            if (in_array('*', $permissions, true)) {
                $permissionIds = Permission::query()->pluck('id')->all();
            } else {
                $permissionIds = Permission::query()
                    ->whereIn('name', $permissions)
                    ->pluck('id')
                    ->all();
            }

            $role->permissions()->sync($permissionIds);
        }
    }
}

