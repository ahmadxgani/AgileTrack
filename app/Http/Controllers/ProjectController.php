<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * List the projects the current user may see.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = Project::query()
            ->visibleTo(request()->user())
            ->withCount('memberships')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'starts_at' => $this->date($project->starts_at),
                'ends_at' => $this->date($project->ends_at),
                'members' => $project->memberships_count,
                'archived_at' => $this->date($project->archived_at),
            ])
            ->all();

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'can' => [
                'create' => Gate::allows('create', Project::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a project.
     */
    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/create', [
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Store a newly created project and its manager membership.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request): Project {
            $project = Project::create([
                ...$request->safe()->only(['name', 'description', 'starts_at', 'ends_at', 'status']),
                'created_by' => $request->user()?->id,
            ]);

            // The creator always keeps a manager row so the project stays
            // reachable through the membership scope.
            $project->memberships()->create([
                'user_id' => $request->user()?->id,
                'project_role' => ProjectRole::Manager,
            ]);

            return $project;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project created.']);

        return to_route('projects.show', $project);
    }

    /**
     * Show a single project with its members.
     */
    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load(['memberships.user', 'creator']);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'starts_at' => $this->date($project->starts_at),
                'ends_at' => $this->date($project->ends_at),
                'archived_at' => $this->date($project->archived_at),
                'creator' => $project->creator?->name,
                'members' => $project->memberships
                    ->sortBy(fn ($membership): string => $membership->user->name)
                    ->map(fn ($membership): array => [
                        'membership_id' => $membership->id,
                        'id' => $membership->user_id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                        'project_role' => $membership->project_role->value,
                        'project_role_label' => $membership->project_role->label(),
                    ])
                    ->values()
                    ->all(),
            ],
            'roles' => array_map(
                fn (ProjectRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                ProjectRole::cases(),
            ),
            'can' => [
                // Archived projects are visible but read only, so both actions
                // are closed on the page as well as on the server.
                'update' => ! $project->isArchived() && Gate::allows('update', $project),
                'manage_members' => ! $project->isArchived() && Gate::allows('manageMembers', $project),
            ],
        ]);
    }

    /**
     * Show the form for editing a project.
     */
    public function edit(Project $project): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('projects/edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status->value,
                'starts_at' => $this->date($project->starts_at),
                'ends_at' => $this->date($project->ends_at),
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Update a project.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        if ($project->isArchived()) {
            throw ValidationException::withMessages(['name' => 'Archived projects are read only.']);
        }

        $project->update($request->safe()->only(['name', 'description', 'starts_at', 'ends_at', 'status']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project updated.']);

        return to_route('projects.show', $project);
    }

    /**
     * Archive a project, which keeps it visible but read only.
     */
    public function archive(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($project->isArchived()) {
            throw ValidationException::withMessages(['name' => 'This project is already archived.']);
        }

        $project->forceFill(['archived_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project archived.']);

        return to_route('projects.show', $project);
    }

    /**
     * Format a date for the frontend.
     */
    private function date(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * Get the selectable statuses.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ProjectStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ProjectStatus::cases(),
        );
    }
}
