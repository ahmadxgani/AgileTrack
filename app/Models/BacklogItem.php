<?php

namespace App\Models;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use Database\Factories\BacklogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * One piece of work in a project backlog.
 *
 * Items are archived instead of deleted so the sprint and review modules
 * added later keep a stable target to point at.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property BacklogPriority $priority
 * @property BacklogStatus $status
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'title', 'description', 'priority', 'status', 'created_by'])]
class BacklogItem extends Model
{
    /** @use HasFactory<BacklogItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => BacklogPriority::class,
            'status' => BacklogStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the project the item belongs to.
     *
     * Named `backlogItems()` on the other side, so a scoped route binding
     * resolves an item only inside the project in the URL.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the account that created the item.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the accounts the item is assigned to.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Determine whether the item is archived, and therefore read only.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Determine whether the item is assigned to the given account.
     */
    public function isAssignedTo(User $user): bool
    {
        if ($this->relationLoaded('assignees')) {
            return $this->assignees->contains('id', $user->id);
        }

        return $this->assignees()->whereKey($user->id)->exists();
    }

    /**
     * Order a list so the most urgent, newest work comes first.
     *
     * The priority column holds a string, so a plain sort would put "low"
     * before "urgent". The CASE expression is standard SQL and runs the
     * same on SQLite and PostgreSQL.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw(
            'case priority when ? then 4 when ? then 3 when ? then 2 else 1 end desc',
            [BacklogPriority::Urgent->value, BacklogPriority::High->value, BacklogPriority::Medium->value],
        )->orderByDesc('created_at')->orderByDesc('id');
    }
}
