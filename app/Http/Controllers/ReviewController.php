<?php

namespace App\Http\Controllers;

use App\Enums\ReviewDecision;
use App\Http\Requests\Reviews\DecideReviewRequest;
use App\Http\Requests\Reviews\StoreReviewRequest;
use App\Http\Requests\Reviews\UpdateReviewRequest;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * Open the reviews of a readable project from the sidebar.
     *
     * The sidebar has no project in its URL, so this picks the first
     * visible one and falls back to the project list when there is none.
     */
    public function shortcut(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $project = Project::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->first();

        return $project === null
            ? to_route('projects.index')
            : to_route('projects.reviews.index', $project);
    }

    /**
     * List the reviews of the project.
     */
    public function index(Project $project): Response
    {
        Gate::authorize('viewAny', [SprintReview::class, $project]);

        $reviews = $project->reviews()
            ->with(['sprint:id,name', 'decider:id,name', 'followUpItem:id,title'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SprintReview $review): array => [
                'id' => $review->id,
                'sprint' => $review->sprint?->name,
                'scheduled_at' => $review->scheduled_at?->format('Y-m-d\TH:i'),
                'decision' => $review->decision->value,
                'decision_label' => $review->decision->label(),
                'decided_by' => $review->decider?->name,
                'follow_up' => $review->followUpItem?->title,
            ])
            ->all();

        return Inertia::render('reviews/index', [
            'project' => $this->projectPayload($project),
            'reviews' => $reviews,
            'can' => [
                'create' => Gate::allows('create', [SprintReview::class, $project]),
            ],
        ]);
    }

    /**
     * Show the form for scheduling a review.
     */
    public function create(Project $project): Response
    {
        Gate::authorize('create', [SprintReview::class, $project]);

        return Inertia::render('reviews/create', [
            'project' => $this->projectPayload($project),
            'sprints' => $this->sprintOptions($project),
        ]);
    }

    /**
     * Store a newly scheduled review.
     */
    public function store(StoreReviewRequest $request, Project $project): RedirectResponse
    {
        $review = $project->reviews()->create([
            ...$request->safe()->only(['sprint_id', 'scheduled_at', 'notes', 'feedback']),
            'decision' => ReviewDecision::Pending,
            'created_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review scheduled.']);

        return to_route('projects.reviews.show', [$project, $review]);
    }

    /**
     * Show a review with its notes, feedback and decision.
     */
    public function show(Project $project, SprintReview $review): Response
    {
        $review->load(['sprint:id,name', 'decider:id,name', 'creator:id,name', 'followUpItem:id,title']);
        $review->setRelation('project', $project);

        Gate::authorize('view', $review);

        return Inertia::render('reviews/show', [
            'project' => $this->projectPayload($project),
            'review' => [
                'id' => $review->id,
                'sprint' => $review->sprint?->name,
                'scheduled_at' => $review->scheduled_at?->format('Y-m-d\TH:i'),
                'notes' => $review->notes,
                'feedback' => $review->feedback,
                'decision' => $review->decision->value,
                'decision_label' => $review->decision->label(),
                'decided_by' => $review->decider?->name,
                'decided_at' => $review->decided_at?->toIso8601String(),
                'created_by' => $review->creator?->name,
                'follow_up' => $review->followUpItem === null ? null : [
                    'id' => $review->followUpItem->id,
                    'title' => $review->followUpItem->title,
                ],
            ],
            'decisions' => array_map(
                fn (ReviewDecision $decision): array => [
                    'value' => $decision->value,
                    'label' => $decision->label(),
                ],
                ReviewDecision::cases(),
            ),
            'can' => [
                'update' => Gate::allows('update', $review),
                'record_feedback' => Gate::allows('recordFeedback', $review),
                'decide' => Gate::allows('decide', $review),
            ],
        ]);
    }

    /**
     * Show the form for editing a review.
     */
    public function edit(Project $project, SprintReview $review): Response
    {
        Gate::authorize('update', $review);

        return Inertia::render('reviews/edit', [
            'project' => $this->projectPayload($project),
            'sprints' => $this->sprintOptions($project),
            'review' => [
                'id' => $review->id,
                // The form uses a sentinel for the whole-project option, so a
                // null sprint becomes an empty string the Select maps to it.
                'sprint_id' => $review->sprint_id === null ? '' : (string) $review->sprint_id,
                'scheduled_at' => $review->scheduled_at?->format('Y-m-d\TH:i') ?? '',
                'notes' => $review->notes ?? '',
                'feedback' => $review->feedback ?? '',
            ],
        ]);
    }

    /**
     * Update the editable fields of a review.
     *
     * A project member may only write the feedback text, so the fields a
     * manager owns are applied only when the actor may update the review.
     */
    public function update(UpdateReviewRequest $request, Project $project, SprintReview $review): RedirectResponse
    {
        DB::transaction(function () use ($request, $review): void {
            // The route binding is a snapshot: the project may have been
            // archived since. Every write re-reads and re-authorizes the
            // locked rows, so a decision is never made on stale state.
            $locked = $this->lockReview($review);

            $fields = $request->safe()->only(['feedback']);

            if (Gate::allows('update', $locked)) {
                $fields = $request->safe()->only(['sprint_id', 'scheduled_at', 'notes', 'feedback']);
            }

            $locked->update($fields);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review updated.']);

        return to_route('projects.reviews.show', [$project, $review]);
    }

    /**
     * Record the decision of the review.
     */
    public function decide(DecideReviewRequest $request, Project $project, SprintReview $review): RedirectResponse
    {
        $review->recordDecision(
            $request->enum('decision', ReviewDecision::class),
            $request->user(),
            $request->input('follow_up_title'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review decided.']);

        return to_route('projects.reviews.show', [$project, $review]);
    }

    /**
     * Re-read the review under a row lock so two requests cannot act on a
     * state the other one just changed.
     *
     * The project row is taken first, the order every other writer uses.
     */
    private function lockReview(SprintReview $review): SprintReview
    {
        $project = Project::query()->whereKey($review->project_id)->lockForUpdate()->firstOrFail();

        $locked = SprintReview::query()
            ->whereKey($review->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $locked->setRelation('project', $project);

        return $locked;
    }

    /**
     * Get the sprints of the project a review may cover.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function sprintOptions(Project $project): array
    {
        return $project->sprints()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->map(fn (Sprint $sprint): array => ['id' => $sprint->id, 'name' => $sprint->name])
            ->all();
    }

    /**
     * Format the project for the frontend.
     *
     * @return array{id: int, name: string, archived_at: string|null}
     */
    private function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'archived_at' => $project->archived_at?->toIso8601String(),
        ];
    }
}
