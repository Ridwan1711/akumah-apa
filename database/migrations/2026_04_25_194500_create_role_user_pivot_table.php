<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
        });

        $now = now();
        $rows = DB::table('users')
            ->select('id as user_id', 'role_id')
            ->whereNotNull('role_id')
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'role_id' => (int) $row->role_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if (! empty($rows)) {
            DB::table('role_user')->upsert(
                $rows,
                ['user_id', 'role_id'],
                ['updated_at']
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
