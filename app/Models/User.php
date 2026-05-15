<?php

namespace App\Models;

use App\Models\Diniyyah\ClassPromotionRecap;
use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\KitabReadingAssessment;
use App\Models\Diniyyah\KitabReadingExaminerAssignment;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\TeacherAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
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
        'homeroom_signature_path',
        'official_photo_path',
        'custom_photo_path',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $appends = [
        'role',
        'profile_photo_url',
        'official_photo_url',
        'custom_photo_url',
        'has_official_photo',
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

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $path = $this->custom_photo_path ?: $this->official_photo_path;

        return $this->publicUrlForPhotoPath($path);
    }

    public function getOfficialPhotoUrlAttribute(): ?string
    {
        return $this->publicUrlForPhotoPath($this->official_photo_path);
    }

    public function getCustomPhotoUrlAttribute(): ?string
    {
        return $this->publicUrlForPhotoPath($this->custom_photo_path);
    }

    private function publicUrlForPhotoPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function getHasOfficialPhotoAttribute(): bool
    {
        return is_string($this->official_photo_path) && $this->official_photo_path !== '';
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

    /**
     * Backward-compatibility accessor used by existing controllers (`$user->guardian`).
     * Returns the primary guardian row linked by `guardians.user_id`.
     */
    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /**
     * All guardians directly linked by legacy `guardians.user_id`.
     */
    public function guardianRecords(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /**
     * New many-to-many mapping (one account can be attached to multiple guardians).
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_user')
            ->withTimestamps();
    }

    /**
     * Resolve guardian record for wali account from new/legacy mappings.
     */
    public function primaryGuardian(): ?Guardian
    {
        if ($this->relationLoaded('guardians') && $this->guardians->isNotEmpty()) {
            return $this->guardians->first();
        }

        $mapped = $this->guardians()->first();
        if ($mapped) {
            return $mapped;
        }

        if ($this->relationLoaded('guardian')) {
            return $this->guardian;
        }

        return $this->guardian()->first();
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

    public function kitabReadingAssessments(): HasMany
    {
        return $this->hasMany(KitabReadingAssessment::class, 'examiner_id');
    }

    public function kitabReadingExaminerAssignments(): HasMany
    {
        return $this->hasMany(KitabReadingExaminerAssignment::class, 'examiner_id');
    }

    public function submittedClassPromotionRecaps(): HasMany
    {
        return $this->hasMany(ClassPromotionRecap::class, 'submitted_by');
    }

    public function reviewedClassPromotionRecaps(): HasMany
    {
        return $this->hasMany(ClassPromotionRecap::class, 'reviewed_by');
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

    public function teacherLocationLogs(): HasMany
    {
        return $this->hasMany(TeacherLocationLog::class, 'teacher_id');
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
            || $this->teacherAssignments()->exists()
            || $this->kitabReadingExaminerAssignments()->exists();
    }
}
