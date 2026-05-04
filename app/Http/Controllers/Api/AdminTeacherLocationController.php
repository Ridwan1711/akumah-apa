<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TeacherLocationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AdminTeacherLocationController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $this->authorizeAdminLocationAccess($request, 'latest');

        $latest = TeacherLocationLog::query()
            ->with('teacher:id,name')
            ->select('teacher_id')
            ->selectRaw('MAX(recorded_at) as latest_recorded_at')
            ->groupBy('teacher_id')
            ->get();

        if ($latest->isEmpty()) {
            return response()->json([
                'latest_locations' => [],
            ]);
        }

        $lookup = TeacherLocationLog::query()
            ->with('teacher:id,name')
            ->where(function ($query) use ($latest) {
                foreach ($latest as $row) {
                    $query->orWhere(function ($sub) use ($row) {
                        $sub->where('teacher_id', $row->teacher_id)
                            ->where('recorded_at', $row->latest_recorded_at);
                    });
                }
            })
            ->orderByDesc('recorded_at')
            ->get();

        return response()->json([
            'latest_locations' => $lookup->map(fn (TeacherLocationLog $log) => $this->payload($log))->values(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $this->authorizeAdminLocationAccess($request, 'history');

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $dateFrom = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : now()->subDays(7)->startOfDay();
        $dateTo = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $limit = (int) ($validated['limit'] ?? 200);

        $logs = TeacherLocationLog::query()
            ->with('teacher:id,name')
            ->when(isset($validated['teacher_id']), fn ($q) => $q->where('teacher_id', (int) $validated['teacher_id']))
            ->whereBetween('recorded_at', [$dateFrom, $dateTo])
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'period' => [
                'date_from' => $dateFrom->toDateTimeString(),
                'date_to' => $dateTo->toDateTimeString(),
            ],
            'logs' => $logs->map(fn (TeacherLocationLog $log) => $this->payload($log))->values(),
        ]);
    }

    private function authorizeAdminLocationAccess(Request $request, string $action): void
    {
        $user = $request->user();
        abort_unless(
            $user && $user->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK, 'admin_pendidikan']),
            403,
            'Anda tidak berhak mengakses data lokasi guru.'
        );

        Log::info('Admin teacher location endpoint accessed', [
            'user_id' => $user->id,
            'action' => $action,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TeacherLocationLog $log): array
    {
        return [
            'id' => $log->id,
            'teacher_id' => $log->teacher_id,
            'teacher_name' => $log->teacher?->name,
            'recorded_at' => $log->recorded_at?->toIso8601String(),
            'latitude' => $log->latitude,
            'longitude' => $log->longitude,
            'accuracy_meters' => $log->accuracy_meters,
            'source' => $log->source,
            'app_state' => $log->app_state,
            'is_location_enabled' => $log->is_location_enabled,
            'note' => $log->note,
        ];
    }
}
