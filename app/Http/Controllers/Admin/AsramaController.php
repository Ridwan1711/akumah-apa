<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\DormAssignment;
use App\Models\DormBuilding;
use App\Models\DormRoom;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AsramaController extends Controller
{
    public function index(): Response
    {
        $selectedAcademicYearId = $this->resolveAcademicYearId();

        $buildings = DormBuilding::with(['rooms' => function ($q) use ($selectedAcademicYearId) {
            $q->withCount(['assignments as occupants_count' => fn ($sq) => $sq->activeInAcademicYear($selectedAcademicYearId)])
              ->with('musyrif.user:id,name');
        }])->orderBy('name')->get();

        return Inertia::render('admin/asrama/index', [
            'buildings' => $buildings,
            'academicYears' => $this->academicYears(),
            'selectedAcademicYearId' => $selectedAcademicYearId,
        ]);
    }

    public function storeBuilding(Request $request): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string']);
        DormBuilding::create($request->only('name', 'description'));

        return redirect()->back()->with('success', 'Gedung berhasil ditambahkan.');
    }

    public function destroyBuilding(DormBuilding $building): RedirectResponse
    {
        $building->delete();

        return redirect()->back()->with('success', 'Gedung berhasil dihapus.');
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $request->validate([
            'building_id' => 'required|exists:dorm_buildings,id',
            'room_number' => 'required|string|max:20',
            'capacity' => 'required|integer|min:1|max:50',
            'floor' => 'nullable|integer|min:1',
        ]);
        DormRoom::create($request->only('building_id', 'room_number', 'capacity', 'floor'));

        return redirect()->back()->with('success', 'Kamar berhasil ditambahkan.');
    }

    public function destroyRoom(DormRoom $room): RedirectResponse
    {
        $room->delete();

        return redirect()->back()->with('success', 'Kamar berhasil dihapus.');
    }

    public function assign(Request $request): Response
    {
        $selectedAcademicYearId = $this->resolveAcademicYearId((int) $request->input('academic_year_id', 0));
        $perPageOptions = [25, 50, 75, 100, 1000];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $unassigned = Student::where('status', Student::STATUS_ACTIVE)
            ->whereDoesntHave('dormAssignments', fn ($q) => $q->activeInAcademicYear($selectedAcademicYearId))
            ->orderBy('full_name')
            ->paginate($perPage, ['id', 'nis', 'full_name'])
            ->withQueryString();

        $rooms = DormRoom::with('building:id,name')
            ->withCount(['assignments as occupants_count' => fn ($q) => $q->activeInAcademicYear($selectedAcademicYearId)])
            ->get()
            ->filter(fn ($r) => $r->occupants_count < $r->capacity);

        $sourceAcademicYear = AcademicYear::query()
            ->where('id', '<', $selectedAcademicYearId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first(['id', 'name']);

        return Inertia::render('admin/asrama/assign', [
            'unassignedStudents' => $unassigned,
            'availableRooms' => $rooms->values(),
            'filters' => $request->only(['per_page', 'academic_year_id']),
            'perPageOptions' => $perPageOptions,
            'academicYears' => $this->academicYears(),
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'copySourceAcademicYear' => $sourceAcademicYear,
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|distinct|exists:students,id',
            'room_id' => 'required|exists:dorm_rooms,id',
            'checkin_date' => 'required|date',
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $academicYearId = (int) $request->input('academic_year_id');
        $studentIds = collect($request->student_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $room = DormRoom::query()->findOrFail((int) $request->room_id);
        $occupied = DormAssignment::query()->where('room_id', $room->id)->activeInAcademicYear($academicYearId)->count();
        $availableSlots = max(0, $room->capacity - $occupied);
        $assignCount = $studentIds->count();

        if ($assignCount > $availableSlots) {
            return redirect()->back()->with('error', "Kapasitas kamar tidak cukup. Slot tersedia {$availableSlots}, dipilih {$assignCount} santri.");
        }

        DB::transaction(function () use ($studentIds, $room, $request) {
            DormAssignment::query()
                ->whereIn('student_id', $studentIds)
                ->activeInAcademicYear((int) $request->academic_year_id)
                ->update(['checkout_date' => now()->toDateString()]);

            $rows = $studentIds->map(fn ($studentId) => [
                'student_id' => $studentId,
                'room_id' => $room->id,
                'academic_year_id' => (int) $request->academic_year_id,
                'checkin_date' => $request->checkin_date,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DormAssignment::query()->insert($rows);
        });

        return redirect()->route('admin.asrama.index')->with('success', "{$assignCount} santri berhasil ditempatkan ke kamar.");
    }

    public function copyAssignmentsFromAcademicYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'target_academic_year_id' => ['required', 'integer', 'exists:academic_years,id', 'different:source_academic_year_id'],
            'checkin_date' => ['nullable', 'date'],
        ]);

        $sourceAcademicYearId = (int) $validated['source_academic_year_id'];
        $targetAcademicYearId = (int) $validated['target_academic_year_id'];
        $checkinDate = (string) ($validated['checkin_date'] ?? now()->toDateString());

        $sourceAssignments = DormAssignment::query()
            ->activeInAcademicYear($sourceAcademicYearId)
            ->orderBy('id')
            ->get(['student_id', 'room_id']);

        if ($sourceAssignments->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data assignment aktif di tahun ajaran sumber.');
        }

        $targetStudentSet = DormAssignment::query()
            ->activeInAcademicYear($targetAcademicYearId)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $rooms = DormRoom::query()->get(['id', 'capacity'])->keyBy('id');
        $roomOccupancy = DormAssignment::query()
            ->activeInAcademicYear($targetAcademicYearId)
            ->selectRaw('room_id, COUNT(*) as total')
            ->groupBy('room_id')
            ->pluck('total', 'room_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        $created = 0;
        $skippedExisting = 0;
        $skippedCapacity = 0;
        $skippedRoomMissing = 0;
        $rows = [];

        foreach ($sourceAssignments as $assignment) {
            $studentId = (int) $assignment->student_id;
            $roomId = (int) $assignment->room_id;

            if (isset($targetStudentSet[$studentId])) {
                $skippedExisting++;
                continue;
            }

            $room = $rooms->get($roomId);
            if (! $room) {
                $skippedRoomMissing++;
                continue;
            }

            $currentOccupancy = (int) ($roomOccupancy[$roomId] ?? 0);
            if ($currentOccupancy >= (int) $room->capacity) {
                $skippedCapacity++;
                continue;
            }

            $roomOccupancy[$roomId] = $currentOccupancy + 1;
            $targetStudentSet[$studentId] = true;
            $rows[] = [
                'student_id' => $studentId,
                'room_id' => $roomId,
                'academic_year_id' => $targetAcademicYearId,
                'checkin_date' => $checkinDate,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $created++;
        }

        if (! empty($rows)) {
            DormAssignment::query()->insert($rows);
        }

        return redirect()->back()->with('success', "Copy assignment selesai. Ditambahkan {$created}, skip existing {$skippedExisting}, skip kapasitas {$skippedCapacity}, skip kamar tidak ditemukan {$skippedRoomMissing}.");
    }

    public function checkout(DormAssignment $assignment): RedirectResponse
    {
        $academicYearId = (int) request()->input('academic_year_id', 0);
        if ($academicYearId > 0 && (int) $assignment->academic_year_id !== $academicYearId) {
            return redirect()->back()->with('error', 'Assignment tidak sesuai dengan tahun ajaran terpilih.');
        }

        $assignment->update(['checkout_date' => now()->toDateString()]);

        return redirect()->back()->with('success', 'Santri berhasil di-checkout.');
    }

    private function resolveAcademicYearId(?int $requestedAcademicYearId = null): int
    {
        if (($requestedAcademicYearId ?? 0) > 0 && AcademicYear::query()->whereKey($requestedAcademicYearId)->exists()) {
            return (int) $requestedAcademicYearId;
        }

        $activeAcademicYearId = AcademicPeriod::query()
            ->active()
            ->value('academic_year_id');
        if ($activeAcademicYearId) {
            return (int) $activeAcademicYearId;
        }

        return (int) (AcademicYear::query()->orderByDesc('start_date')->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id'));
    }

    private function academicYears()
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'start_date', 'end_date']);
    }
}
