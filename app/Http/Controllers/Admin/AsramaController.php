<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DormAssignment;
use App\Models\DormBuilding;
use App\Models\DormRoom;
use App\Models\Musyrif;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AsramaController extends Controller
{
    public function index(): Response
    {
        $buildings = DormBuilding::with(['rooms' => function ($q) {
            $q->withCount(['activeAssignments as occupants_count'])
              ->with('musyrif.user:id,name');
        }])->orderBy('name')->get();

        return Inertia::render('admin/asrama/index', [
            'buildings' => $buildings,
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
            'capacity' => 'required|integer|min:1|max:20',
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
        $perPageOptions = [25, 50, 75, 100, 1000];
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $unassigned = Student::where('status', Student::STATUS_ACTIVE)
            ->whereDoesntHave('dormAssignments', fn ($q) => $q->whereNull('checkout_date'))
            ->orderBy('full_name')
            ->paginate($perPage, ['id', 'nis', 'full_name'])
            ->withQueryString();

        $rooms = DormRoom::with('building:id,name')
            ->withCount(['activeAssignments as occupants_count'])
            ->get()
            ->filter(fn ($r) => $r->occupants_count < $r->capacity);

        return Inertia::render('admin/asrama/assign', [
            'unassignedStudents' => $unassigned,
            'availableRooms' => $rooms->values(),
            'filters' => $request->only(['per_page']),
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|distinct|exists:students,id',
            'room_id' => 'required|exists:dorm_rooms,id',
            'checkin_date' => 'required|date',
        ]);

        $studentIds = collect($request->student_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $room = DormRoom::query()->findOrFail((int) $request->room_id);
        $occupied = DormAssignment::query()->where('room_id', $room->id)->active()->count();
        $availableSlots = max(0, $room->capacity - $occupied);
        $assignCount = $studentIds->count();

        if ($assignCount > $availableSlots) {
            return redirect()->back()->with('error', "Kapasitas kamar tidak cukup. Slot tersedia {$availableSlots}, dipilih {$assignCount} santri.");
        }

        DB::transaction(function () use ($studentIds, $room, $request) {
            DormAssignment::query()
                ->whereIn('student_id', $studentIds)
                ->active()
                ->update(['checkout_date' => now()->toDateString()]);

            $rows = $studentIds->map(fn ($studentId) => [
                'student_id' => $studentId,
                'room_id' => $room->id,
                'checkin_date' => $request->checkin_date,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DormAssignment::query()->insert($rows);
        });

        return redirect()->route('admin.asrama.index')->with('success', "{$assignCount} santri berhasil ditempatkan ke kamar.");
    }

    public function checkout(DormAssignment $assignment): RedirectResponse
    {
        $assignment->update(['checkout_date' => now()->toDateString()]);

        return redirect()->back()->with('success', 'Santri berhasil di-checkout.');
    }
}
