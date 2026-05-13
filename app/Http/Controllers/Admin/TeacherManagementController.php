<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkAssignTeachersRequest;
use App\Http\Requests\Admin\BulkSetTeacherActiveRequest;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TeacherManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $guruRoleId = (int) Role::query()->where('name', Role::GURU)->value('id');
        $query = User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', Role::GURU))
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = (string) $request->string('search');
                $builder->where(function ($inner) use ($search) {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($request->input('status') === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->orderBy('name');

        return Inertia::render('admin/teachers/index', [
            'teachers' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'importRuns' => ImportRun::query()
                ->with('requestedBy:id,name')
                ->where('type', ImportRun::TYPE_TEACHERS)
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function eligibleUsers(Request $request): JsonResponse
    {
        $guruRoleId = (int) Role::query()->where('name', Role::GURU)->value('id');
        $search = trim((string) $request->string('search'));
        $limit = min(200, max(1, (int) $request->query('limit', 25)));

        $users = User::query()
            ->when($guruRoleId > 0, fn (Builder $builder) => $builder->whereDoesntHave('roles', fn (Builder $roleQuery) => $roleQuery->where('roles.id', $guruRoleId)))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function bulkAssign(BulkAssignTeachersRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $guruRoleId = (int) Role::query()->where('name', Role::GURU)->value('id');
        $ids = array_map('intval', $validated['user_ids']);
        $isActive = (bool) ($validated['is_active'] ?? true);

        DB::transaction(function () use ($ids, $guruRoleId, $isActive) {
            $users = User::query()->whereIn('id', $ids)->lockForUpdate()->get();
            foreach ($users as $user) {
                $user->update(['is_active' => $isActive]);
                $user->roles()->syncWithoutDetaching([$guruRoleId]);
            }
        });

        $n = count($ids);

        return redirect()->route('admin.teachers.index')
            ->with('success', "{$n} user berhasil ditetapkan sebagai guru.");
    }

    public function bulkSetActive(BulkSetTeacherActiveRequest $request): RedirectResponse
    {
        $ids = array_map('intval', $request->validated('teacher_ids'));
        $active = $request->boolean('is_active');

        $users = User::query()->whereIn('id', $ids)->get();
        if ($users->count() !== count(array_unique($ids))) {
            return redirect()->route('admin.teachers.index')
                ->with('error', 'Beberapa ID guru tidak ditemukan.');
        }
        foreach ($users as $user) {
            if (! $user->hasRole(Role::GURU)) {
                return redirect()->route('admin.teachers.index')
                    ->with('error', 'Tindakan massal hanya untuk akun ber-role Guru.');
            }
        }

        DB::transaction(function () use ($users, $active) {
            foreach ($users as $user) {
                $user->update(['is_active' => $active]);
            }
        });

        $verb = $active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.teachers.index')
            ->with('success', count($ids).' guru berhasil '.$verb.'.');
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $guruRoleId = (int) Role::query()->where('name', Role::GURU)->value('id');

        if (($validated['mode'] ?? 'create') === 'assign') {
            $teacher = User::query()->findOrFail((int) $validated['existing_user_id']);
            if (array_key_exists('is_active', $validated)) {
                $teacher->update([
                    'is_active' => (bool) $validated['is_active'],
                ]);
            }
        } else {
            $teacher = User::query()->create([
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'must_change_password' => true,
            ]);
        }
        $teacher->roles()->syncWithoutDetaching([$guruRoleId]);

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Guru berhasil ditambahkan.');
    }

    public function update(UpdateTeacherRequest $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->hasRole(Role::GURU), 404);
        $validated = $request->validated();
        $guruRoleId = (int) Role::query()->where('name', Role::GURU)->value('id');

        $teacher->update([
            'name' => $validated['name'],
            'username' => $validated['username'] ?? null,
            'email' => $validated['email'],
            'is_active' => (bool) ($validated['is_active'] ?? $teacher->is_active),
        ]);
        $teacher->roles()->syncWithoutDetaching([$guruRoleId]);

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Data guru berhasil diperbarui.');
    }

    public function toggleActive(User $teacher): RedirectResponse
    {
        abort_unless($teacher->hasRole(Role::GURU), 404);
        $teacher->update([
            'is_active' => ! $teacher->is_active,
        ]);

        $status = $teacher->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.teachers.index')
            ->with('success', "Guru {$teacher->name} berhasil {$status}.");
    }
}
