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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
 * @property int|null $sprint_id
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'title', 'description', 'priority', 'status', 'sprint_id', 'created_by'])]
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
     * Get the sprint the item is planned into, if any.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the recorded status moves of the item.
     *
     * @return HasMany<BacklogStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(BacklogStatusHistory::class);
    }

    /**
     * Move the item to another status and record the move.
     *
     * Every caller goes through here, so the item and its history can never
     * disagree. The rows are taken in the same order as the sprint actions
     * (project, then sprint, then item), so concurrent changes queue up
     * instead of deadlocking, and each check reads the locked state.
     *
     * The status board means a move, so repeating the current status is an
     * error there. The edit form submits the whole item and may well resend
     * the status it holds, so it passes false and gets a quiet no-op, still
     * without a history row.
     *
     * @throws ValidationException
     */
    public function transitionStatus(
        BacklogStatus $status,
        ?User $actor = null,
        bool $rejectUnchanged = true,
    ): void {
        DB::transaction(function () use ($status, $actor, $rejectUnchanged): void {
            // Read without a lock only to learn which project to lock first.
            // The project id of an item never changes.
            $projectId = static::query()
                ->whereKey($this->getKey())
                ->value('project_id');

            $project = Project::query()->whereKey($projectId)->lockForUpdate()->firstOrFail();

            // Sprint membership only changes in actions that take this same
            // project lock first, so the second read cannot go stale.
            /** @var self $hint */
            $hint = static::query()
                ->select(['id', 'sprint_id'])
                ->whereKey($this->getKey())
                ->firstOrFail();

            $sprint = $hint->sprint_id === null
                ? null
                : Sprint::query()->whereKey($hint->sprint_id)->lockForUpdate()->first();

            /** @var self $item */
            $item = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            // Authorize the locked rows, not the snapshot the caller holds: an
            // assignment removed or a project archived since then must stop
            // the change here, whatever the request already checked.
            if ($actor !== null) {
                $item->setRelation('project', $project);

                Gate::forUser($actor)->authorize('updateStatus', $item);
            }

            if ($item->status === $status) {
                if (! $rejectUnchanged) {
                    return;
                }

                throw ValidationException::withMessages([
                    'status' => 'The item already has that status.',
                ]);
            }

            if ($sprint !== null && ! $sprint->status->acceptsItemChanges()) {
                throw ValidationException::withMessages([
                    'status' => 'The sprint of this item is closed.',
                ]);
            }

            $from = $item->status;

            $item->forceFill(['status' => $status])->save();

            $item->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $status,
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);
        });
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
        $cases = [];
        $bindings = [];

        foreach (BacklogPriority::cases() as $priority) {
            $cases[] = 'when ? then ?';
            $bindings[] = $priority->value;
            $bindings[] = $priority->weight();
        }

        $query->orderByRaw('case priority '.implode(' ', $cases).' end desc', $bindings)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
