<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
