<?php

namespace App\Models;

use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\TeacherAssignment;
use App\Support\Authorization\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'whatsapp_phone',
        'google_connected',
        'password',
        'is_active',
        'must_change_password',
        'must_complete_profile',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $appends = [
        'role',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'must_complete_profile' => 'boolean',
            'google_connected' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'model_has_permissions', 'model_id', 'permission_id')
            ->wherePivot('model_type', self::class)
            ->withPivotValue('model_type', self::class);
    }

    public function permissionScopes(): HasMany
    {
        return $this->hasMany(PermissionScope::class);
    }

    /**
     * Transitional compatibility attribute while migrating from single-role.
     */
    public function getRoleAttribute(): ?Role
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->first();
        }

        return $this->roles()->first();
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id');
    }

    public function homeroomAssignments(): HasMany
    {
        return $this->hasMany(ClassWali::class, 'teacher_id');
    }

    public function scoresEntered(): HasMany
    {
        return $this->hasMany(Score::class, 'teacher_id');
    }

    public function musyrif(): HasOne
    {
        return $this->hasOne(Musyrif::class);
    }

    // --- Kehadiran ---

    public function lessonSessionsCreated(): HasMany
    {
        return $this->hasMany(LessonSession::class, 'created_by');
    }

    // --- Push notification (FCM) ---

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Route notifications for the FCM channel.
     *
     * @return array<int, string>
     */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }

    public function hasRole(string ...$roles): bool
    {
        if (empty($roles)) {
            return false;
        }

        $currentRoles = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->get(['roles.name']);

        return $currentRoles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole(...$roles);
    }

    public function hasAllRoles(array $roles): bool
    {
        if (empty($roles)) {
            return false;
        }

        $currentRoles = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->get(['roles.name']);
        $names = $currentRoles->pluck('name');

        foreach ($roles as $role) {
            if (! $names->contains($role)) {
                return false;
            }
        }

        return true;
    }

    public static function isSuperAdminExclusiveViolation(array $roleNames): bool
    {
        $uniqueRoleNames = collect($roleNames)
            ->filter(fn ($role) => is_string($role) && $role !== '')
            ->unique()
            ->values();

        return $uniqueRoleNames->contains(Role::SUPER_ADMIN) && $uniqueRoleNames->count() > 1;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(...Role::ADMIN_ROLES);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function getAllPermissionNames(): array
    {
        $directPermissions = $this->permissions()
            ->pluck('permissions.name')
            ->all();

        $rolePermissions = Permission::query()
            ->whereHas('roles.users', fn ($query) => $query->where('users.id', $this->id))
            ->pluck('name')
            ->all();

        return collect([...$directPermissions, ...$rolePermissions])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($permission === '') {
            return false;
        }

        return in_array($permission, $this->getAllPermissionNames(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessKitabGrades(): bool
    {
        return $this->hasPermission('kitab_grades.view_all')
            || $this->teacherAssignments()->exists();
    }
}
