<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->index('user_id');
        });

        Schema::create('guardian_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['guardian_id', 'user_id']);
            $table->index(['user_id', 'guardian_id']);
        });

        $rows = DB::table('guardians')
            ->whereNotNull('user_id')
            ->get(['id', 'user_id'])
            ->map(fn ($row) => [
                'guardian_id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if (! empty($rows)) {
            DB::table('guardian_user')->upsert(
                $rows,
                ['guardian_id', 'user_id'],
                ['updated_at']
            );
        }
    }

    public function down(): void
    {
        $firstGuardianByUser = DB::table('guardians')
            ->whereNotNull('user_id')
            ->selectRaw('MIN(id) as keep_guardian_id, user_id')
            ->groupBy('user_id')
            ->pluck('keep_guardian_id', 'user_id');

        foreach ($firstGuardianByUser as $userId => $keepGuardianId) {
            DB::table('guardians')
                ->where('user_id', $userId)
                ->where('id', '!=', $keepGuardianId)
                ->update(['user_id' => null]);
        }

        Schema::dropIfExists('guardian_user');

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->unique('user_id');
        });
    }
};
