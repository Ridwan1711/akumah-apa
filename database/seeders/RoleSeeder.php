<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::SUPER_ADMIN,
            Role::ADMIN_AKADEMIK,
            Role::ADMIN_KEUANGAN,
            Role::ADMIN_KEUANGAN_OBSERVER,
            Role::MUSYRIF,
            Role::GURU,
            Role::SANTRI,
            Role::WALI_SANTRI,
            Role::ALUMNI,
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
