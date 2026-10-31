<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A project worked on by the team.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property ProjectStatus $status
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'starts_at', 'ends_at', 'status', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => ProjectStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the account that created the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the membership rows of the project.
     *
     * @return HasMany<ProjectMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMembership::class);
    }

    /**
     * Determine whether the project is archived, and therefore read only.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Limit the query to the projects the user may see.
     *
     * Administrators and owner-level roles see everything; everyone else
     * sees only the projects they belong to.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (self::seesEveryProject($user)) {
            return;
        }

        $query->whereHas('memberships', fn (Builder $memberships) => $memberships->where('user_id', $user->id));
    }

    /**
     * Determine whether the user may see every project.
     */
    public static function seesEveryProject(User $user): bool
    {
        return $user->is_administrator
            || $user->hasRole(UserRole::Owner)
            || $user->hasRole(UserRole::ProductOwner);
    }
}
