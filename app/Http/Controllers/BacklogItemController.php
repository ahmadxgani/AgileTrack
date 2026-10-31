<?php

namespace App\Http\Controllers;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Http\Requests\Backlog\StoreBacklogItemRequest;
use App\Http\Requests\Backlog\UpdateBacklogItemRequest;
use App\Http\Requests\Backlog\UpdateBacklogStatusRequest;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BacklogItemController extends Controller
{
    /**
     * Open the backlog of a project the user may read.
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
            : to_route('projects.backlog.index', $project);
    }

    /**
     * List the backlog of the project.
     */
    public function index(Project $project): Response
    {
        Gate::authorize('viewAny', [BacklogItem::class, $project]);

        $project->loadMissing('memberships');

        $items = $project->backlogItems()
            ->with(['assignees:id,name'])
            ->ordered()
            ->get()
            ->each(fn (BacklogItem $item) => $item->setRelation('project', $project))
            ->map(fn (BacklogItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'priority' => $item->priority->value,
                'priority_label' => $item->priority->label(),
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'archived_at' => $item->archived_at?->toIso8601String(),
                'assignees' => $item->assignees
                    ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
                    ->values()
                    ->all(),
                'can' => [
                    'update' => Gate::allows('update', $item),
                    'update_status' => Gate::allows('updateStatus', $item),
                    'archive' => Gate::allows('archive', $item),
                ],
            ])
            ->all();

        return Inertia::render('backlog/index', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'archived_at' => $project->archived_at?->toIso8601String(),
            ],
            'items' => $items,
            'statuses' => $this->statusOptions(),
            'can' => [
                'create' => Gate::allows('create', [BacklogItem::class, $project]),
            ],
        ]);
    }

    /**
     * Show the form for creating a backlog item.
     */
    public function create(Project $project): Response
    {
        Gate::authorize('create', [BacklogItem::class, $project]);

        return Inertia::render('backlog/create', [
            'project' => ['id' => $project->id, 'name' => $project->name],
            'members' => $this->memberOptions($project),
            'priorities' => $this->priorityOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Store a backlog item and its assignees.
     */
    public function store(StoreBacklogItemRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            $item = $project->backlogItems()->create([
                'title' => $request->string('title')->toString(),
                'description' => $request->input('description'),
                'priority' => $request->enum('priority', BacklogPriority::class) ?? BacklogPriority::Medium,
                'status' => $request->enum('status', BacklogStatus::class) ?? BacklogStatus::Todo,
                'created_by' => $request->user()?->id,
            ]);

            $item->assignees()->sync($request->input('assignees', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Backlog item created.']);

        return to_route('projects.backlog.index', $project);
    }

    /**
     * Show the form for editing a backlog item.
     */
    public function edit(Project $project, BacklogItem $backlogItem): Response
    {
        Gate::authorize('update', $backlogItem);

        $backlogItem->load('assignees:id,name');

        return Inertia::render('backlog/edit', [
            'project' => ['id' => $project->id, 'name' => $project->name],
            'item' => [
                'id' => $backlogItem->id,
                'title' => $backlogItem->title,
                'description' => $backlogItem->description,
                'priority' => $backlogItem->priority->value,
                'status' => $backlogItem->status->value,
                'assignees' => $backlogItem->assignees->modelKeys(),
            ],
            'members' => $this->memberOptions($project),
            'priorities' => $this->priorityOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Update the editable fields of a backlog item.
     */
    public function update(
        UpdateBacklogItemRequest $request,
        Project $project,
        BacklogItem $backlogItem,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $backlogItem): void {
            $backlogItem->update([
                'title' => $request->string('title')->toString(),
                'description' => $request->input('description'),
                'priority' => $request->enum('priority', BacklogPriority::class),
                'status' => $request->enum('status', BacklogStatus::class),
            ]);

            // Absent means "leave the assignment alone"; an empty list clears it.
            if ($request->has('assignees')) {
                $backlogItem->assignees()->sync($request->input('assignees', []));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Backlog item updated.']);

        return to_route('projects.backlog.index', $project);
    }

    /**
     * Move a single item across the Kanban columns.
     *
     * This is the one change an assigned team member may make.
     */
    public function status(
        UpdateBacklogStatusRequest $request,
        Project $project,
        BacklogItem $backlogItem,
    ): RedirectResponse {
        $backlogItem->update(['status' => $request->enum('status', BacklogStatus::class)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status updated.']);

        return to_route('projects.backlog.index', $project);
    }

    /**
     * Archive a backlog item, which keeps it visible but read only.
     */
    public function archive(Project $project, BacklogItem $backlogItem): RedirectResponse
    {
        Gate::authorize('archive', $backlogItem);

        if ($backlogItem->isArchived()) {
            throw ValidationException::withMessages(['title' => 'This item is already archived.']);
        }

        $backlogItem->forceFill(['archived_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Backlog item archived.']);

        return to_route('projects.backlog.index', $project);
    }

    /**
     * Get the project members who may be assigned work.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function memberOptions(Project $project): array
    {
        return $project->memberships()
            ->with('user:id,name')
            ->get()
            ->sortBy(fn ($membership): string => $membership->user->name)
            ->map(fn ($membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Get the selectable priorities.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function priorityOptions(): array
    {
        return array_map(
            fn (BacklogPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()],
            BacklogPriority::cases(),
        );
    }

    /**
     * Get the selectable statuses.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (BacklogStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            BacklogStatus::cases(),
        );
    }
}
