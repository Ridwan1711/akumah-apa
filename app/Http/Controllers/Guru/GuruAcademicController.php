<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Diniyyah\AcademicSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuruAcademicController extends Controller
{
    private function dayName(int $day): string
    {
        return match ($day) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
            default => 'Unknown',
        };
    }

    public function schedule(Request $request): Response
    {
        $user = $request->user();
        $activeSemester = AcademicPeriod::query()->active()->with('semester:id,name')->first()?->semester;

        $schedules = AcademicSchedule::query()
            ->where('teacher_id', $user->id)
            ->whereIn('day', AcademicSchedule::TEACHING_DAYS)
            ->with([
                'schoolClass:id,name,level',
                'subject:id,name',
            ])
            ->orderBy('day')
            ->orderBy('time_start')
            ->get();

        $week = [];
        foreach ($schedules as $item) {
            $day = (int) $item->day;

            if (! isset($week[$day])) {
                $week[$day] = [
                    'day_of_week' => $day,
                    'day_name' => $this->dayName($day),
                    'entries' => [],
                ];
            }

            $week[$day]['entries'][] = [
                'id' => $item->id,
                'class' => [
                    'id' => $item->schoolClass?->id,
                    'name' => $item->schoolClass?->name,
                    'level' => $item->schoolClass?->level,
                ],
                'subject' => [
                    'id' => $item->subject?->id,
                    'name' => $item->subject?->name,
                ],
                'start_time' => $item->time_start,
                'end_time' => $item->time_end,
                'room' => null,
            ];
        }

        ksort($week);

        return Inertia::render('guru/schedule', [
            'teacher' => $user->only(['id', 'name']),
            'semester' => $activeSemester ? $activeSemester->only(['id', 'name']) : null,
            'week' => array_values($week),
        ]);
    }
}
