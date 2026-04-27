<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();

        $user = User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@siakad.test',
                'password' => 'password',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );
        $user->roles()->sync([$role->id]);
    }
}
