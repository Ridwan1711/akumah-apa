<?php

namespace App\Http\Controllers\Admin;

use App\Actions\TeacherPresence\SendTeacherPresenceOverlayDemoPush;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendManualNotificationRequest;
use App\Jobs\DispatchAdminManualNotificationJob;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AdminManualNotification;
use App\Support\Authorization\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AdminNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizePage($request);

        $search = trim((string) $request->input('search', ''));
        $users = $this->queryUsersWithTokens($search, 40);

        return Inertia::render('admin/notifications/manual', [
            'filters' => ['search' => $search],
            'users' => $users->map(fn (User $user) => $this->transformUser($user))->values(),
            'summary' => [
                'users_with_tokens' => User::query()->where('is_active', true)->whereHas('deviceTokens')->count(),
                'device_tokens' => DeviceToken::query()->count(),
            ],
        ]);
    }

    public function previewTargets(Request $request): JsonResponse
    {
        $this->authorizePage($request);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $userIds = collect($validated['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $users = $this->queryUsersWithTokens($search, 80, $userIds);
        $eligibleTokenCount = $users->sum(fn (User $user) => $user->deviceTokens->count());

        return response()->json([
            'users' => $users->map(fn (User $user) => $this->transformUser($user))->values(),
            'summary' => [
                'eligible_users' => $users->count(),
                'eligible_device_tokens' => $eligibleTokenCount,
            ],
        ]);
    }

    public function send(SendManualNotificationRequest $request): RedirectResponse
    {
        $this->authorizePage($request);
        $validated = $request->validated();

        $targets = $this->resolveTargets(
            (string) $validated['target_mode'],
            collect($validated['user_ids'] ?? [])->map(fn ($id) => (int) $id)->all(),
            collect($validated['device_token_ids'] ?? [])->map(fn ($id) => (int) $id)->all(),
        );

        if (empty($targets)) {
            return redirect()->back()->with('error', 'Tidak ada target valid dengan token aktif.');
        }

        $title = (string) $validated['title'];
        $body = (string) $validated['body'];
        $deeplink = (string) ($validated['deeplink'] ?? '/notifications');
        $totalDeviceTargets = collect($targets)->sum(fn ($target) => count($target['device_token_ids']));

        if ($totalDeviceTargets <= 1) {
            $this->dispatchSync($title, $body, $deeplink, $targets);

            return redirect()->back()->with('success', 'Notifikasi berhasil dikirim langsung ke target.');
        }

        DispatchAdminManualNotificationJob::dispatch($title, $body, $deeplink, $targets);

        return redirect()->back()->with('success', "Notifikasi bulk masuk antrean ({$totalDeviceTargets} device target).");
    }

    public function sendTeacherPresenceOverlayDemo(
        Request $request,
        SendTeacherPresenceOverlayDemoPush $action,
    ): RedirectResponse {
        $this->authorizePage($request);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'lesson_session_id' => ['nullable', 'integer', 'exists:lesson_sessions,id'],
        ]);

        $teacher = User::query()
            ->whereKey((int) $validated['user_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $sessionId = isset($validated['lesson_session_id'])
            ? (int) $validated['lesson_session_id']
            : null;

        try {
            $session = $action->execute($teacher, $sessionId);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'demo_overlay' => [$e->getMessage()],
            ]);
        }

        return redirect()->back()->with(
            'success',
            "Push demo overlay (data-only) terkirim ke {$teacher->name} (sesi #{$session->id}). ".
            'Di Android overlay bisa muncul di background jika izin tampil di atas aplikasi lain sudah diberikan.',
        );
    }

    private function authorizePage(Request $request): void
    {
        abort_unless($request->user()?->hasPermission(Permissions::NOTIFICATION_MANUAL_SEND), 403);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function queryUsersWithTokens(string $search = '', int $limit = 40, array $userIds = [])
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('deviceTokens')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(! empty($userIds), fn ($query) => $query->whereIn('id', $userIds))
            ->with([
                'roles:id,name',
                'deviceTokens' => fn ($query) => $query
                    ->select(['id', 'user_id', 'platform', 'device_label', 'last_used_at', 'updated_at'])
                    ->orderByDesc('updated_at'),
            ])
            ->withCount('deviceTokens')
            ->orderByDesc('device_tokens_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'username', 'email']);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->values(),
            'device_tokens_count' => (int) $user->device_tokens_count,
            'device_tokens' => $user->deviceTokens->map(fn (DeviceToken $token) => [
                'id' => $token->id,
                'platform' => $token->platform,
                'device_label' => $token->device_label,
                'last_used_at' => optional($token->last_used_at)?->toIso8601String(),
                'updated_at' => optional($token->updated_at)?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $deviceTokenIds
     * @return array<int, array{user_id:int,device_token_ids:array<int,int>}>
     */
    private function resolveTargets(string $targetMode, array $userIds, array $deviceTokenIds): array
    {
        if ($targetMode === 'specific_devices') {
            return DeviceToken::query()
                ->whereIn('id', $deviceTokenIds)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get(['id', 'user_id'])
                ->groupBy('user_id')
                ->map(fn ($tokens, $userId) => [
                    'user_id' => (int) $userId,
                    'device_token_ids' => $tokens->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ])
                ->values()
                ->all();
        }

        if ($targetMode === 'single_user') {
            $userIds = array_slice(array_values(array_unique($userIds)), 0, 1);
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->with(['deviceTokens:id,user_id'])
            ->get(['id'])
            ->filter(fn (User $user) => $user->deviceTokens->isNotEmpty())
            ->map(fn (User $user) => [
                'user_id' => (int) $user->id,
                'device_token_ids' => $user->deviceTokens
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{user_id:int,device_token_ids:array<int,int>}>  $targets
     */
    private function dispatchSync(string $title, string $body, string $deeplink, array $targets): void
    {
        $users = User::query()
            ->whereIn('id', collect($targets)->pluck('user_id')->values()->all())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        foreach ($targets as $target) {
            /** @var User|null $user */
            $user = $users->get((int) $target['user_id']);
            if (! $user) {
                continue;
            }

            $user->notify(new AdminManualNotification(
                titleText: $title,
                bodyText: $body,
                deeplink: $deeplink,
                deviceTokenIds: $target['device_token_ids'],
            ));
        }
    }
}
