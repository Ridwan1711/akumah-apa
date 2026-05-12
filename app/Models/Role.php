<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN_AKADEMIK = 'admin_akademik';
    public const ADMIN_KEUANGAN = 'admin_keuangan';

    /** Baca tagihan terbatas (invoice.view_limited); tanpa pembayaran/laporan penuh. */
    public const ADMIN_KEUANGAN_OBSERVER = 'admin_keuangan_observer';

    public const MUSYRIF = 'musyrif';
    public const GURU = 'guru';
    public const SANTRI = 'santri';
    public const WALI_SANTRI = 'wali_santri';
    public const ALUMNI = 'alumni';
    public const ADMIN_ROLES = [
        self::SUPER_ADMIN,
        self::ADMIN_AKADEMIK,
        self::ADMIN_KEUANGAN,
        self::ADMIN_KEUANGAN_OBSERVER,
    ];

    public const SANTRI_ROLES = [
        self::SANTRI,
        self::WALI_SANTRI,
        self::ALUMNI,
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }
}
