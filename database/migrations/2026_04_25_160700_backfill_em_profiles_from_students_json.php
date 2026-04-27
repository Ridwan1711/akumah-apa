<?php

use App\Models\EmProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'em_profile')) {
            return;
        }

        $rows = DB::table('students')
            ->whereNotNull('em_profile')
            ->get(['id', 'em_profile']);

        foreach ($rows as $row) {
            $payload = $row->em_profile;
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                $payload = is_array($decoded) ? $decoded : [];
            }

            if (! is_array($payload) || $payload === []) {
                continue;
            }

            $attributes = EmProfile::fromPayload($payload);
            DB::table('em_profiles')->updateOrInsert(
                ['student_id' => $row->id],
                array_merge($attributes, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid removing migrated profile data.
    }
};
