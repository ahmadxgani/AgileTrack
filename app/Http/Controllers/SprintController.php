<?php

namespace App\Http\Controllers;

use App\Enums\BacklogStatus;
use App\Enums\SprintStatus;
use App\Http\Requests\Backlog\UpdateBacklogStatusRequest;
use App\Http\Requests\Sprints\SprintItemRequest;
use App\Http\Requests\Sprints\StoreSprintRequest;
use App\Http\Requests\Sprints\UpdateSprintRequest;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    /**
     * Open the sprints of a readable project from the sidebar.
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
            : to_route('sprints.index', $project);
    }

    /**
     * List the sprints of the project.
     */
    public function index(Project $project): Response
    {
        Gate::authorize('viewAny', [Sprint::class, $project]);

        $sprints = $project->sprints()
            ->withCount('backlogItems')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Sprint $sprint): array => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'goal' => $sprint->goal,
                'starts_at' => $this->date($sprint->starts_at),
                'ends_at' => $this->date($sprint->ends_at),
                'status' => $sprint->status->value,
                'status_label' => $sprint->status->label(),
                'items' => $sprint->backlog_items_count,
            ])
            ->all();

        return Inertia::render('sprints/index', [
            'project' => $this->projectPayload($project),
            'sprints' => $sprints,
            'can' => [
                'create' => Gate::allows('create', [Sprint::class, $project]),
            ],
        ]);
    }

    /**
     * Show the form for creating a sprint.
     */
    public function create(Project $project): Response
    {
        Gate::authorize('create', [Sprint::class, $project]);

        return Inertia::render('sprints/create', [
            'project' => $this->projectPayload($project),
        ]);
    }

    /**
     * Store a newly created sprint.
     */
    public function store(StoreSprintRequest $request, Project $project): RedirectResponse
    {
        $sprint = $project->sprints()->create([
            ...$request->safe()->only(['name', 'goal', 'starts_at', 'ends_at']),
            'status' => SprintStatus::Planned,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sprint created.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Show the planning view and the Kanban board of the sprint.
     */
    public function show(Project $project, Sprint $sprint): Response
    {
        Gate::authorize('viewAny', [Sprint::class, $project]);

        $items = $sprint->backlogItems()
            ->with('assignees:id,name')
            ->ordered()
            ->get();

        $pool = $project->backlogItems()
            ->whereNull('sprint_id')
            ->whereNull('archived_at')
            ->ordered()
            ->get(['id', 'title', 'priority', 'status']);

        return Inertia::render('sprints/show', [
            'project' => $this->projectPayload($project),
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'goal' => $sprint->goal,
                'starts_at' => $this->date($sprint->starts_at),
                'ends_at' => $this->date($sprint->ends_at),
                'status' => $sprint->status->value,
                'status_label' => $sprint->status->label(),
            ],
            'board' => $this->board($sprint, $items),
            'pool' => $pool->map(fn (BacklogItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'priority_label' => $item->priority->label(),
            ])->all(),
            'statuses' => array_map(
                fn (BacklogStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                BacklogStatus::cases(),
            ),
            'can' => [
                'update' => Gate::allows('update', $sprint),
                'start' => Gate::allows('start', $sprint),
                'close' => Gate::allows('close', $sprint),
                'manage_items' => Gate::allows('manageItems', $sprint)
                    && $sprint->status->acceptsItemChanges(),
            ],
        ]);
    }

    /**
     * Show the form for editing a sprint.
     */
    public function edit(Project $project, Sprint $sprint): Response
    {
        Gate::authorize('update', $sprint);

        return Inertia::render('sprints/edit', [
            'project' => $this->projectPayload($project),
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'goal' => $sprint->goal,
                'starts_at' => $this->date($sprint->starts_at),
                'ends_at' => $this->date($sprint->ends_at),
            ],
        ]);
    }

    /**
     * Update the editable fields of a sprint.
     */
    public function update(UpdateSprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        DB::transaction(function () use ($request, $sprint): void {
            // The route binding is a snapshot: the sprint may have been closed
            // since. Every action re-reads and re-authorizes the locked rows,
            // so a decision is never made on stale state.
            $locked = $this->lockSprint($sprint);

            Gate::authorize('update', $locked);

            $locked->update($request->safe()->only(['name', 'goal', 'starts_at', 'ends_at']));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sprint updated.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Start the sprint, which makes it the single active sprint of the project.
     */
    public function start(Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('start', $sprint);

        DB::transaction(function () use ($project, $sprint): void {
            // lockSprint takes the project row first, which is the shared
            // slot: it serialises two managers starting two different
            // sprints of the same project at the same moment.
            $locked = $this->lockSprint($sprint);

            Gate::authorize('start', $locked);

            $running = $project->sprints()
                ->whereKeyNot($locked->getKey())
                ->where('status', SprintStatus::Active->value)
                ->exists();

            if ($running) {
                throw ValidationException::withMessages([
                    'sprint' => 'Another sprint of this project is already active.',
                ]);
            }

            $locked->update(['status' => SprintStatus::Active]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sprint started.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Close the sprint, which freezes its items.
     */
    public function close(Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('close', $sprint);

        DB::transaction(function () use ($sprint): void {
            $locked = $this->lockSprint($sprint);

            Gate::authorize('close', $locked);

            $locked->update(['status' => SprintStatus::Closed]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sprint closed.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Add a backlog item of the project to the sprint.
     */
    public function addItem(SprintItemRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        DB::transaction(function () use ($request, $sprint): void {
            $locked = $this->lockSprint($sprint);

            Gate::authorize('manageItems', $locked);

            $this->ensureAcceptsItems($locked);

            $item = $locked->project->backlogItems()
                ->whereKey($request->integer('backlog_item_id'))
                ->lockForUpdate()
                ->first();

            // Only unplanned work joins: an item of another project or one
            // already in a sprint is rejected instead of being moved silently.
            if ($item === null || $item->sprint_id !== null || $item->isArchived()) {
                throw ValidationException::withMessages([
                    'backlog_item_id' => 'Only unplanned items of this project can join the sprint.',
                ]);
            }

            $item->sprint()->associate($locked);
            $item->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item added to the sprint.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Remove a backlog item from the sprint, which returns it to the pool.
     */
    public function removeItem(SprintItemRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        DB::transaction(function () use ($request, $sprint): void {
            $locked = $this->lockSprint($sprint);

            Gate::authorize('manageItems', $locked);

            $this->ensureAcceptsItems($locked);

            $item = $locked->backlogItems()
                ->whereKey($request->integer('backlog_item_id'))
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    'backlog_item_id' => 'That item is not in this sprint.',
                ]);
            }

            $item->sprint()->dissociate();
            $item->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item removed from the sprint.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Move an item across the Kanban columns.
     */
    public function updateItemStatus(
        UpdateBacklogStatusRequest $request,
        Project $project,
        Sprint $sprint,
        BacklogItem $backlogItem,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $sprint, $backlogItem): void {
            $locked = $this->lockSprint($sprint);

            $item = $locked->backlogItems()
                ->whereKey($backlogItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $item->transitionStatus(
                $request->enum('status', BacklogStatus::class),
                $request->user(),
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status updated.']);

        return to_route('sprints.show', [$project, $sprint]);
    }

    /**
     * Re-read the sprint under a row lock so two requests cannot act on a
     * state the other one just changed.
     *
     * The project row is taken first. Every other writer locks in the same
     * order (project, then sprint, then item), so two requests never wait
     * on each other's rows.
     */
    private function lockSprint(Sprint $sprint): Sprint
    {
        Project::query()->whereKey($sprint->project_id)->lockForUpdate()->firstOrFail();

        return Sprint::query()
            ->with('project.memberships')
            ->whereKey($sprint->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Reject item changes on a sprint that no longer accepts them.
     *
     * A closed sprint is a state conflict, not a missing permission, so it
     * reports as a validation error on the form.
     *
     * @throws ValidationException
     */
    private function ensureAcceptsItems(Sprint $sprint): void
    {
        if (! $sprint->status->acceptsItemChanges()) {
            throw ValidationException::withMessages([
                'backlog_item_id' => 'The sprint is closed.',
            ]);
        }
    }

    /**
     * Group the sprint items into the three board columns.
     *
     * @param  Collection<int, BacklogItem>  $items
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function board(Sprint $sprint, Collection $items): array
    {
        $columns = array_fill_keys(
            array_map(fn (BacklogStatus $status): string => $status->value, BacklogStatus::cases()),
            [],
        );

        foreach ($items as $item) {
            $columns[$item->status->value][] = [
                'id' => $item->id,
                'title' => $item->title,
                'priority' => $item->priority->value,
                'priority_label' => $item->priority->label(),
                'assignees' => $item->assignees
                    ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                    ->values()
                    ->all(),
                // A closed sprint freezes its items even for their manager.
                'can_move_status' => $sprint->status->acceptsItemChanges()
                    && Gate::allows('updateStatus', $item),
            ];
        }

        return $columns;
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

    /**
     * Format a date for the frontend.
     */
    private function date(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}
