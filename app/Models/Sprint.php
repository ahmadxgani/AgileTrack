<?php

namespace App\Models;

use App\Enums\SprintStatus;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One time box of work inside a project.
 *
 * At most one sprint per project is active at a time. A closed sprint is
 * read only: its items may neither be swapped nor moved.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $goal
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property SprintStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'name', 'goal', 'starts_at', 'ends_at', 'status'])]
class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
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
            'status' => SprintStatus::class,
        ];
    }

    /**
     * Get the project the sprint belongs to.
     *
     * Named `sprints()` on the other side, so a scoped route binding
     * resolves a sprint only inside the project in the URL.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the backlog items planned into the sprint.
     *
     * @return HasMany<BacklogItem, $this>
     */
    public function backlogItems(): HasMany
    {
        return $this->hasMany(BacklogItem::class);
    }

    /**
     * Determine whether the sprint is closed, and therefore read only.
     */
    public function isClosed(): bool
    {
        return $this->status === SprintStatus::Closed;
    }
}
