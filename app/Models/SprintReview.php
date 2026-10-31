<?php

namespace App\Models;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ReviewDecision;
use App\Policies\ReviewPolicy;
use Database\Factories\SprintReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The record of a project or sprint review.
 *
 * A review starts pending, collects notes and feedback from the team, and
 * ends with a decision. The decision names the person and the moment that
 * made it; asking for changes files the follow-up work in the backlog.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $sprint_id
 * @property Carbon|null $scheduled_at
 * @property string|null $notes
 * @property string|null $feedback
 * @property ReviewDecision $decision
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $created_by
 * @property int|null $follow_up_backlog_item_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id',
    'sprint_id',
    'scheduled_at',
    'notes',
    'feedback',
    'decision',
    'created_by',
])]
#[UsePolicy(ReviewPolicy::class)]
class SprintReview extends Model
{
    /** @use HasFactory<SprintReviewFactory> */
    use HasFactory;

    /**
     * Store datetimes with microsecond precision, so the stamped decision
     * moment round-trips exactly instead of being truncated to the second.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'decided_at' => 'datetime',
            'decision' => ReviewDecision::class,
        ];
    }

    /**
     * Get the project the review belongs to.
     *
     * Named `reviews()` on the other side, so a scoped route binding
     * resolves a review only inside the project in the URL.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the sprint the review covers, if any.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the account that made the decision.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Get the account that scheduled the review.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the backlog item filed by a "needs changes" decision.
     *
     * @return BelongsTo<BacklogItem, $this>
     */
    public function followUpItem(): BelongsTo
    {
        return $this->belongsTo(BacklogItem::class, 'follow_up_backlog_item_id');
    }

    /**
     * Decide the review and file the follow-up work in one transaction.
     *
     * A decided review names the actor and the moment; going back to
     * pending clears both. Asking for changes needs a follow-up item: one
     * already linked is reused, otherwise the caller supplies a title and
     * the item is created here, so a decision can never leave the feedback
     * without work behind it.
     *
     * The project row is locked before the review, the same order the sprint
     * actions take, so concurrent writers queue up instead of deadlocking.
     * The result is re-authorized on the locked rows, because the request
     * may have been checked against a state that has since changed.
     *
     * @throws ValidationException
     */
    public function recordDecision(
        ReviewDecision $decision,
        User $actor,
        ?string $followUpTitle = null,
    ): void {
        DB::transaction(function () use ($decision, $actor, $followUpTitle): void {
            $project = Project::query()
                ->whereKey($this->project_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var self $review */
            $review = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $review->setRelation('project', $project);

            Gate::forUser($actor)->authorize('decide', $review);

            $review->forceFill([
                'decision' => $decision,
                'decided_by' => $decision->isDecided() ? $actor->id : null,
                'decided_at' => $decision->isDecided() ? now() : null,
            ]);

            if ($decision === ReviewDecision::NeedsChanges
                && $review->follow_up_backlog_item_id === null) {
                if ($followUpTitle === null || $followUpTitle === '') {
                    throw ValidationException::withMessages([
                        'follow_up_title' => 'Asking for changes needs a follow-up backlog item title.',
                    ]);
                }

                $item = $project->backlogItems()->create([
                    'title' => $followUpTitle,
                    'priority' => BacklogPriority::Medium,
                    'status' => BacklogStatus::Todo,
                    'created_by' => $actor->id,
                ]);

                $review->follow_up_backlog_item_id = $item->id;
            }

            $review->save();
        });
    }
}
