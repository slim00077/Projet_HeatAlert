<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_RESIDENT = 'resident';

    public const ROLES = [self::ROLE_RESIDENT, self::ROLE_MANAGER, self::ROLE_ADMIN];

    /**
     * The role is intentionally not mass assignable: new users default to resident.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Uploaded profile picture URL, or null so views show an initials avatar.
     */
    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path !== null
            ? Storage::disk('public')->url($this->profile_photo_path)
            : null;
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->name, 0, 1));
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isResident(): bool
    {
        return $this->role === self::ROLE_RESIDENT;
    }

    /**
     * The neighborhood a residence manager is responsible for (assigned by an admin).
     */
    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class);
    }

    /**
     * Residences this resident was added to by a manager.
     */
    public function memberResidences(): BelongsToMany
    {
        return $this->belongsToMany(Residence::class, 'residence_user')->withTimestamps();
    }

    public function residentReports(): HasMany
    {
        return $this->hasMany(ResidentReport::class);
    }
}
