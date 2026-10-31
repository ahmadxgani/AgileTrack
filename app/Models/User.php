<?php

namespace App\Models;

use App\Enums\UserRole as Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read bool $is_administrator
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the roles assigned to the user.
     *
     * @return HasMany<UserRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Determine whether the user holds the given role.
     */
    public function hasRole(Role $role): bool
    {
        return $this->roles->contains('role', $role);
    }

    /**
     * Determine whether the user is the only active administrator left.
     *
     * The system must always keep one account that can manage accounts, so
     * both account deletion and role or status changes call this.
     */
    public function isLastActiveAdministrator(): bool
    {
        if (! $this->is_active || ! $this->hasRole(Role::Administrator)) {
            return false;
        }

        return self::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('role', Role::Administrator->value))
            ->count() === 1;
    }

    /**
     * Determine whether the user is an administrator.
     *
     * @return Attribute<bool, never>
     */
    protected function isAdministrator(): Attribute
    {
        return Attribute::get(fn (): bool => $this->hasRole(Role::Administrator));
    }
}
