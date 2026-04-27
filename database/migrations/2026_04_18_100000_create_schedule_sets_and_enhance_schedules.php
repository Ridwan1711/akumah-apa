<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('jam_count')->default(6);
            $table->unsignedTinyInteger('day_count')->default(6);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_id', 'name']);
            $table->index(['period_id', 'is_active']);
        });

        Schema::create('schedule_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_set_id')->constrained('schedule_sets')->cascadeOnDelete();
            $table->unsignedTinyInteger('jam_no');
            $table->time('time_start');
            $table->time('time_end');
            $table->timestamps();

            $table->unique(['schedule_set_id', 'jam_no']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('schedule_set_id')
                ->nullable()
                ->after('period_id')
                ->constrained('schedule_sets')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('jam_no')->nullable()->after('day');
            $table->char('combined_group_id', 36)->nullable()->after('jam_no');

            $table->index(['schedule_set_id', 'day', 'jam_no']);
            $table->index(['schedule_set_id', 'teacher_id', 'day', 'jam_no']);
            $table->index('combined_group_id');
        });

        $this->backfillExistingSchedules();

        // Enforce unique (schedule_set_id, class_id, day, jam_no) — one cell per slot per class.
        Schema::table('schedules', function (Blueprint $table) {
            $table->unique(['schedule_set_id', 'class_id', 'day', 'jam_no'], 'schedules_set_class_day_jam_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique('schedules_set_class_day_jam_unique');
            $table->dropIndex(['schedule_set_id', 'day', 'jam_no']);
            $table->dropIndex(['schedule_set_id', 'teacher_id', 'day', 'jam_no']);
            $table->dropIndex(['combined_group_id']);
            $table->dropConstrainedForeignId('schedule_set_id');
            $table->dropColumn(['jam_no', 'combined_group_id']);
        });

        Schema::dropIfExists('schedule_time_slots');
        Schema::dropIfExists('schedule_sets');
    }

    /**
     * Migrate existing legacy schedules: group by period, create default schedule set,
     * derive ordinal jam_no from distinct (time_start, time_end) pairs.
     */
    private function backfillExistingSchedules(): void
    {
        $periods = DB::table('schedules')
            ->select('period_id')
            ->distinct()
            ->pluck('period_id');

        foreach ($periods as $periodId) {
            $periodName = DB::table('academic_periods')->where('id', $periodId)->value('name') ?? ('Periode #'.$periodId);

            $setName = 'Jadwal Default '.$periodName;
            $existing = DB::table('schedule_sets')
                ->where('period_id', $periodId)
                ->where('name', $setName)
                ->value('id');

            $setId = $existing ?: DB::table('schedule_sets')->insertGetId([
                'period_id' => $periodId,
                'name' => $setName,
                'jam_count' => 6,
                'day_count' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $slots = DB::table('schedules')
                ->where('period_id', $periodId)
                ->select('time_start', 'time_end')
                ->groupBy('time_start', 'time_end')
                ->orderBy('time_start')
                ->get();

            $slotMap = [];
            $jamNo = 1;
            foreach ($slots as $slot) {
                DB::table('schedule_time_slots')->insert([
                    'schedule_set_id' => $setId,
                    'jam_no' => $jamNo,
                    'time_start' => $slot->time_start,
                    'time_end' => $slot->time_end,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $slotMap[$slot->time_start.'|'.$slot->time_end] = $jamNo;
                $jamNo++;
            }

            if ($jamNo - 1 !== 6) {
                DB::table('schedule_sets')->where('id', $setId)->update([
                    'jam_count' => max($jamNo - 1, 1),
                    'updated_at' => now(),
                ]);
            }

            $rows = DB::table('schedules')->where('period_id', $periodId)->get();
            foreach ($rows as $row) {
                $key = $row->time_start.'|'.$row->time_end;
                $assignedJam = $slotMap[$key] ?? null;
                if ($assignedJam === null) {
                    continue;
                }
                DB::table('schedules')->where('id', $row->id)->update([
                    'schedule_set_id' => $setId,
                    'jam_no' => $assignedJam,
                ]);
            }
        }
    }
};
