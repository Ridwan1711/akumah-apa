<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waliKelasRole = DB::table('roles')->where('name', 'wali_kelas')->first();
        $guruRole = DB::table('roles')->where('name', 'guru')->first();

        if ($waliKelasRole && $guruRole) {
            DB::table('users')
                ->where('role_id', $waliKelasRole->id)
                ->update(['role_id' => $guruRole->id]);
        }

        DB::table('roles')->where('name', 'wali_kelas')->delete();
    }

    public function down(): void
    {
        DB::table('roles')->insertOrIgnore([
            'name' => 'wali_kelas',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
