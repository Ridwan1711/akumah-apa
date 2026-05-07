<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $guruRoleId = Role::query()->where('name', Role::GURU)->value('id');
        $roleIdsFilter = collect($request->input('role_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($request->input('role_name') === Role::GURU && $guruRoleId) {
            $roleIdsFilter[] = (int) $guruRoleId;
            $roleIdsFilter = collect($roleIdsFilter)->unique()->values()->all();
        }

        $query = User::with(['roles', 'permissions'])
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('username', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            }))
            ->when(! empty($roleIdsFilter), fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->whereIn('roles.id', $roleIdsFilter)))
            ->when($request->status !== null && $request->status !== '', fn ($q) => $q->where('is_active', $request->status === '1'))
            ->when($request->filled('has_official_photo') && $request->has_official_photo !== 'all', function ($q) use ($request) {
                if ($request->has_official_photo === '1') {
                    $q->whereNotNull('official_photo_path')->where('official_photo_path', '!=', '');
                } else {
                    $q->where(function ($qq) {
                        $qq->whereNull('official_photo_path')->orWhere('official_photo_path', '');
                    });
                }
            })
            ->orderByDesc('created_at');

        $importRunsQuery = ImportRun::query()
            ->with('requestedBy:id,name')
            ->where('type', ImportRun::TYPE_TEACHERS)
            ->when($request->import_uploader_id, fn ($q, $uploaderId) => $q->where('requested_by', $uploaderId))
            ->latest('id');

        $importUploaderIds = ImportRun::query()
            ->where('type', ImportRun::TYPE_TEACHERS)
            ->whereNotNull('requested_by')
            ->when($request->import_uploader_id, fn ($q, $uploaderId) => $q->where('requested_by', $uploaderId))
            ->distinct()
            ->pluck('requested_by');

        $importUploaders = User::query()
            ->whereIn('id', $importUploaderIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/users/index', [
            'users' => $query->paginate(15)->withQueryString(),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
            'filters' => [
                ...$request->only(['search', 'status', 'import_uploader_id']),
                'has_official_photo' => $request->input('has_official_photo'),
                'role_ids' => $roleIdsFilter,
                'role_name' => $request->input('role_name'),
            ],
            'photoCompliance' => [
                'with_official_photo' => User::query()->whereNotNull('official_photo_path')->where('official_photo_path', '!=', '')->count(),
                'without_official_photo' => User::query()->where(function ($q) {
                    $q->whereNull('official_photo_path')->orWhere('official_photo_path', '');
                })->count(),
            ],
            'isTeacherMode' => in_array((int) $guruRoleId, $roleIdsFilter, true),
            'importRuns' => in_array((int) $guruRoleId, $roleIdsFilter, true)
                ? $importRunsQuery->limit(20)->get()
                : [],
            'importUploaders' => in_array((int) $guruRoleId, $roleIdsFilter, true) ? $importUploaders : [],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_ids' => ['nullable', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'is_active' => ['boolean'],
        ]);
        $roleIds = $this->extractRoleIds($validated);
        $permissionIds = $this->extractPermissionIds($validated);

        $payload = collect($validated)->except(['role_ids', 'permission_ids'])->all();
        $payload['must_change_password'] = true;

        $user = User::create($payload);
        $user->roles()->sync($roleIds);
        $user->permissions()->sync($permissionIds);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user): Response
    {
        $user->load('roles');

        return Inertia::render('admin/users/edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_ids' => ['nullable', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'is_active' => ['boolean'],
        ]);
        $roleIds = $this->extractRoleIds($validated);
        $permissionIds = $this->extractPermissionIds($validated);

        $payload = collect($validated)->except(['role_ids', 'permission_ids'])->all();
        $user->update($payload);
        $user->roles()->sync($roleIds);
        $user->permissions()->sync($permissionIds);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $user->update([
            'password' => Hash::make('password'),
            'must_change_password' => true,
        ]);

        return redirect()->back()->with('success', "Password user {$user->name} berhasil direset.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "User {$user->name} berhasil {$status}.");
    }

    public function uploadOfficialPhoto(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        /** @var UploadedFile $photo */
        $photo = $validated['photo'];
        $oldPath = $user->official_photo_path;
        if (is_string($oldPath) && $oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $stored = $photo->store("profile-photos/{$user->id}", 'public');
        $user->forceFill(['official_photo_path' => $stored])->save();

        return redirect()->back()->with('success', "Foto resmi user {$user->name} berhasil diperbarui.");
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    private function extractRoleIds(array $validated): array
    {
        $roleIds = collect($validated['role_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($roleIds->isEmpty()) {
            throw ValidationException::withMessages([
                'role_ids' => ['Role wajib diisi minimal satu.'],
            ]);
        }

        $roleNames = Role::query()
            ->whereIn('id', $roleIds->all())
            ->pluck('name')
            ->all();
        if (User::isSuperAdminExclusiveViolation($roleNames)) {
            throw ValidationException::withMessages([
                'role_ids' => ['Role super_admin harus eksklusif dan tidak boleh digabung dengan role lain.'],
            ]);
        }

        return $roleIds->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     */
    private function extractPermissionIds(array $validated): array
    {
        return collect($validated['permission_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
