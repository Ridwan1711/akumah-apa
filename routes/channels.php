<?php

use App\Models\Diniyyah\ScheduleSet;
use App\Models\Role;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('schedule.set.{setId}', function ($user, int $setId): ?array {
    if (! $user->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK])) {
        return null;
    }

    $exists = ScheduleSet::query()->whereKey($setId)->exists();
    if (! $exists) {
        return null;
    }

    $roleName = (string) (
        $user->role?->name
        ?? $user->roles()->orderBy('roles.id')->value('roles.name')
        ?? ''
    );

    return [
        'id' => (int) $user->id,
        'name' => (string) ($user->name ?? "User #{$user->id}"),
        'role' => $roleName,
        'color_seed' => (int) (crc32((string) $user->id) % 360),
    ];
});
