<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('roles', 'permissionScopes');
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissionNames() : [],
                'permissionScopes' => $user
                    ? $user->permissionScopes
                        ->map(fn ($scope) => [
                            'permission_name' => $scope->permission_name,
                            'scope_key' => $scope->scope_key,
                            'scope_value' => $scope->scope_value,
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
            'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
            'hasWaliKelasRecord' => $user ? $user->homeroomAssignments()->exists() : false,
            'hasGuruRecord' => $user ? $user->teacherAssignments()->exists() : false,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'generated' => $request->session()->get('generated'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
