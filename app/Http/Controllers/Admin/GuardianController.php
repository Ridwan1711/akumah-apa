<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuardianRequest;
use App\Http\Requests\Admin\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $guardians = Guardian::query()
            ->when($search, fn ($q) => $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%"))
            ->withCount('students')
            ->with('students:id,full_name,nis')
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/guardians/index', [
            'guardians' => $guardians,
            'filters' => ['search' => $search],
        ]);
    }

    public function attach(Student $student): Response
    {
        $attachedGuardianIds = $student->guardians()
            ->pluck('guardians.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingUsers = User::query()
            ->with([
                'roles:id,name',
                'guardian:id,user_id,full_name,phone,email',
                'guardians:id,full_name,phone,email',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'whatsapp_phone', 'is_active'])
            ->map(function (User $user) use ($attachedGuardianIds) {
                $mappedGuardian = $user->guardians->first();
                $legacyGuardian = $user->guardian;
                $guardian = $mappedGuardian ?? $legacyGuardian;
                $roleNames = $user->roles->pluck('name')->values();
                $guardianId = $guardian?->id;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->whatsapp_phone,
                    'is_active' => (bool) $user->is_active,
                    'roles' => $roleNames->all(),
                    'has_guardian_record' => $guardian !== null,
                    'guardian_id' => $guardianId,
                    'guardian_name' => $guardian?->full_name,
                    'guardian_phone' => $guardian?->phone ?? $user->whatsapp_phone,
                    'guardian_email' => $guardian?->email ?? $user->email,
                    'is_already_attached' => $guardianId !== null
                        ? in_array((int) $guardianId, $attachedGuardianIds, true)
                        : false,
                ];
            })
            ->values();

        return Inertia::render('admin/guardians/attach', [
            'student' => $student,
            'existingUsers' => $existingUsers,
        ]);
    }

    public function storeAttach(Request $request, Student $student): RedirectResponse
    {
        $request->validate([
            'guardian_id' => ['nullable', 'required_without:user_id', 'exists:guardians,id'],
            'user_id' => ['nullable', 'required_without:guardian_id', 'exists:users,id'],
            'relationship' => ['required', Rule::in(Guardian::RELATIONSHIPS)],
        ]);

        $guardian = null;
        $resolvedUser = null;
        if ($request->filled('guardian_id')) {
            $guardian = Guardian::findOrFail((int) $request->guardian_id);
            $resolvedUser = $guardian->user;
        } else {
            $user = User::query()
                ->with(['guardians:id,user_id', 'guardian:id,user_id'])
                ->findOrFail((int) $request->user_id);
            $resolvedUser = $user;

            $guardian = $user->guardians->first() ?? $user->guardian;
            if (! $guardian) {
                $guardian = Guardian::create([
                    'user_id' => $user->id,
                    'full_name' => $user->name,
                    'phone' => $user->whatsapp_phone,
                    'email' => $user->email,
                    'relationship' => $request->relationship,
                ]);
            }
        }

        /** @var Guardian $guardian */
        abort_if($student->guardians()->where('guardians.id', $guardian->id)->exists(), 422, 'Wali ini sudah terdaftar untuk santri ini.');

        if ($resolvedUser instanceof User) {
            $waliRoleId = (int) Role::query()->where('name', Role::WALI_SANTRI)->value('id');
            abort_unless($waliRoleId > 0, 422, 'Role wali_santri belum tersedia.');
            $resolvedUser->roles()->syncWithoutDetaching([$waliRoleId]);

            if (Schema::hasTable('guardian_user')) {
                $resolvedUser->guardians()->syncWithoutDetaching([$guardian->id]);
            }
        }

        $guardian->students()->attach($student->id, ['relationship' => $request->relationship]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Wali berhasil ditambahkan ke santri ini.');
    }

    public function create(Student $student): Response
    {
        return Inertia::render('admin/guardians/create', [
            'student' => $student,
        ]);
    }

    public function store(StoreGuardianRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $relationship = $data['relationship'] ?? 'wali';
        unset($data['relationship']);

        $guardian = Guardian::create(array_merge($data, ['relationship' => $relationship]));
        $guardian->students()->attach($student->id, ['relationship' => $relationship]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data wali santri berhasil ditambahkan.');
    }

    public function edit(Student $student, Guardian $guardian): Response
    {
        $pivot = $guardian->students()->where('students.id', $student->id)->first()?->pivot;
        $guardian->relationship = $pivot?->relationship ?? 'wali';

        return Inertia::render('admin/guardians/edit', [
            'student' => $student,
            'guardian' => $guardian,
        ]);
    }

    public function update(UpdateGuardianRequest $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $data = $request->validated();
        $relationship = $data['relationship'] ?? 'wali';
        unset($data['relationship']);

        $guardian->update(array_merge($data, ['relationship' => $relationship]));
        $guardian->students()->updateExistingPivot($student->id, ['relationship' => $relationship]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data wali santri berhasil diperbarui.');
    }

    public function destroy(Student $student, Guardian $guardian): RedirectResponse
    {
        $guardian->students()->detach($student->id);
        if ($guardian->students()->count() === 0 && ! $guardian->user_id) {
            $guardian->delete();
        }

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Data wali santri berhasil dihapus.');
    }
}
