<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AnnouncementSegmentedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserManagementController extends Controller
{
    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json([
            'roles' => $roles,
            'guru_role_id' => Role::query()->where('name', Role::GURU)->value('id'),
        ]);
    }

    public function users(Request $request): JsonResponse
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

        $query = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->input('search'));
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(! empty($roleIdsFilter), fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->whereIn('roles.id', $roleIdsFilter)))
            ->when($request->filled('status') && $request->status !== 'all', function ($q) use ($request) {
                $status = $this->normalizeStatus($request->input('status'));
                if ($status !== null) {
                    $q->where('is_active', $status);
                }
            })
            ->orderByDesc('created_at');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $users = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'guard_name']),
            'filters' => [
                ...$request->only(['search', 'status']),
                'role_ids' => $roleIdsFilter,
                'role_name' => $request->input('role_name'),
            ],
            'is_teacher_mode' => in_array((int) $guruRoleId, $roleIdsFilter, true),
            'guru_role_id' => $guruRoleId,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_ids' => ['nullable', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $roleIds = $this->extractRoleIds($validated);

        $payload = collect($validated)
            ->except(['role_ids'])
            ->all();
        $payload['must_change_password'] = true;

        $user = User::query()->create($payload);
        $user->roles()->sync($roleIds);
        $user->load('roles');

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'user' => $user,
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_ids' => ['nullable', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $roleIds = $this->extractRoleIds($validated);
        $payload = collect($validated)
            ->except(['role_ids'])
            ->all();

        $user->update($payload);
        $user->roles()->sync($roleIds);
        $user->load('roles');

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'user' => $user,
        ]);
    }

    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "User {$user->name} berhasil {$status}.",
            'user' => $user->load('roles'),
        ]);
    }

    public function resetPassword(User $user): JsonResponse
    {
        $user->update([
            'password' => Hash::make('password'),
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => "Password user {$user->name} berhasil direset.",
            'default_password' => 'password',
            'user' => $user->load('roles'),
        ]);
    }

    public function uploadOfficialPhoto(Request $request, User $user): JsonResponse
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
        $user->refresh();

        return response()->json([
            'message' => "Foto resmi user {$user->name} berhasil diperbarui.",
            'user' => $user->load('roles'),
        ]);
    }

    public function sendAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_target' => ['required', Rule::in(['santri', 'guru', 'wali', 'admin', 'multi'])],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:255'],
        ]);

        $roleTarget = (string) $validated['role_target'];
        $url = isset($validated['url']) && $validated['url'] !== '' ? (string) $validated['url'] : '/notifications';

        $query = User::query()->where('is_active', true);
        if ($roleTarget !== 'multi') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', $this->roleNamesForTarget($roleTarget)));
        }

        $sent = 0;
        $query->chunkById(200, function ($users) use (&$sent, $validated, $roleTarget, $url) {
            foreach ($users as $user) {
                /** @var User $user */
                $user->notify(new AnnouncementSegmentedNotification(
                    titleText: (string) $validated['title'],
                    bodyText: (string) $validated['body'],
                    roleTarget: $roleTarget,
                    url: $url,
                ));
                $sent++;
            }
        });

        return response()->json([
            'message' => 'Pengumuman berhasil dikirim.',
            'sent_count' => $sent,
            'role_target' => $roleTarget,
        ]);
    }

    public function notificationTokenDiagnostics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_target' => ['nullable', Rule::in(['santri', 'guru', 'wali', 'admin', 'multi'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'only_without_tokens' => ['nullable', 'boolean'],
            'include_tokens' => ['nullable', 'boolean'],
        ]);

        $roleTarget = (string) ($validated['role_target'] ?? 'multi');
        $perPage = (int) ($validated['per_page'] ?? 50);
        $onlyWithoutTokens = filter_var($validated['only_without_tokens'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $includeTokens = filter_var($validated['include_tokens'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = User::query()
            ->where('is_active', true)
            ->with('roles:id,name')
            ->withCount('deviceTokens')
            ->when($roleTarget !== 'multi', fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->whereIn('name', $this->roleNamesForTarget($roleTarget))))
            ->when($onlyWithoutTokens, fn ($q) => $q->having('device_tokens_count', '=', 0))
            ->orderByDesc('device_tokens_count')
            ->orderBy('name');

        $users = $query->paginate($perPage)->withQueryString();

        $payloadUsers = collect($users->items())->map(function (User $user) use ($includeTokens) {
            $item = [
                'user_id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'roles' => $user->roles->pluck('name')->values(),
                'token_count' => (int) $user->device_tokens_count,
                'has_tokens' => (int) $user->device_tokens_count > 0,
            ];

            if ($includeTokens) {
                $item['tokens'] = DeviceToken::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('updated_at')
                    ->get(['id', 'platform', 'device_label', 'last_used_at', 'updated_at']);
            }

            return $item;
        })->values();

        $activeBase = User::query()
            ->where('is_active', true)
            ->when($roleTarget !== 'multi', fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->whereIn('name', $this->roleNamesForTarget($roleTarget))));

        $activeCount = (clone $activeBase)->count();
        $withTokensCount = (clone $activeBase)->whereHas('deviceTokens')->count();

        return response()->json([
            'summary' => [
                'role_target' => $roleTarget,
                'active_users' => $activeCount,
                'users_with_tokens' => $withTokensCount,
                'users_without_tokens' => max(0, $activeCount - $withTokensCount),
            ],
            'users' => [
                'data' => $payloadUsers,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ],
            ],
            'filters' => [
                'role_target' => $roleTarget,
                'only_without_tokens' => $onlyWithoutTokens,
                'include_tokens' => $includeTokens,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function normalizeStatus(mixed $status): ?bool
    {
        return match ((string) $status) {
            '1', 'true', 'active' => true,
            '0', 'false', 'inactive' => false,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int>
     *
     * @throws ValidationException
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
            ->pluck('name');

        if (User::isSuperAdminExclusiveViolation($roleNames->all())) {
            throw ValidationException::withMessages([
                'role_ids' => ['Role super_admin harus eksklusif dan tidak boleh digabung dengan role lain.'],
            ]);
        }

        return $roleIds->all();
    }

    /**
     * @return array<int, string>
     */
    private function roleNamesForTarget(string $target): array
    {
        return match ($target) {
            'santri' => [Role::SANTRI],
            'guru' => [Role::GURU],
            'wali' => [Role::WALI_SANTRI],
            'admin' => Role::ADMIN_ROLES,
            default => [],
        };
    }
}
