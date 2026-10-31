<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Http\Requests\Projects\StoreProjectMemberRequest;
use App\Http\Requests\Projects\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProjectMemberController extends Controller
{
    /**
     * Add an existing active account to the project.
     */
    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->ensureNotArchived($project);

        $user = User::query()
            ->where('email', mb_strtolower($request->string('email')->toString()))
            ->where('is_active', true)
            ->first();

        // Validation already checked this; the guard only covers an account
        // disappearing between validation and the insert.
        abort_if($user === null, 404);

        $project->memberships()->create([
            'user_id' => $user->id,
            'project_role' => $request->string('project_role')->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member added.']);

        return to_route('projects.show', $project);
    }

    /**
     * Change the project role of a member.
     */
    public function update(
        UpdateProjectMemberRequest $request,
        Project $project,
        ProjectMembership $membership,
    ): RedirectResponse {
        $this->ensureBelongsToProject($project, $membership);
        $this->ensureNotArchived($project);

        DB::transaction(function () use ($request, $project, $membership): void {
            $this->ensureAManagerRemains($project, $membership, $request->enum('project_role', ProjectRole::class));

            $membership->update(['project_role' => $request->string('project_role')->toString()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member role updated.']);

        return to_route('projects.show', $project);
    }

    /**
     * Remove a member from the project.
     */
    public function destroy(Project $project, ProjectMembership $membership): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        $this->ensureBelongsToProject($project, $membership);
        $this->ensureNotArchived($project);

        DB::transaction(function () use ($project, $membership): void {
            $this->ensureAManagerRemains($project, $membership, null);

            // A removed member must stop being an assignee of this project's
            // backlog: otherwise their name stays on the item and the row
            // silently comes back if they are added again.
            DB::table('backlog_item_user')
                ->where('user_id', $membership->user_id)
                ->whereIn('backlog_item_id', $project->backlogItems()->select('id'))
                ->delete();

            $membership->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Member removed.']);

        return to_route('projects.show', $project);
    }

    /**
     * Ensure the membership row belongs to the project in the URL.
     */
    private function ensureBelongsToProject(Project $project, ProjectMembership $membership): void
    {
        abort_unless($membership->project_id === $project->id, 404);
    }

    /**
     * Reject changes to an archived project the same way project edits are.
     */
    private function ensureNotArchived(Project $project): void
    {
        if ($project->isArchived()) {
            throw ValidationException::withMessages([
                'project_role' => 'Archived projects are read only.',
            ]);
        }
    }

    /**
     * Reject demoting or removing the last manager of a project.
     *
     * A project without a manager cannot be edited by anyone but an
     * administrator, so this keeps the project in the hands of its team.
     * Null means the row is being deleted rather than given a new role.
     *
     * Runs inside the caller's transaction, which locks the project row
     * before re-reading the membership: without that, two concurrent
     * demotions could each see the other manager and both succeed.
     * SQLite ignores the lock, but it serialises writers anyway.
     */
    private function ensureAManagerRemains(
        Project $project,
        ProjectMembership $membership,
        ?ProjectRole $nextRole,
    ): void {
        Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

        // Re-read under the project lock rather than refresh(): another
        // manager may have removed this row between the route binding and
        // this transaction, and refresh() would raise a bare
        // ModelNotFoundException (a 404) instead of a clean message.
        $current = $project->memberships()->whereKey($membership->getKey())->lockForUpdate()->first();

        if ($current === null) {
            throw ValidationException::withMessages([
                'project_role' => 'That member is no longer in this project.',
            ]);
        }

        if ($current->project_role !== ProjectRole::Manager || $nextRole === ProjectRole::Manager) {
            return;
        }

        $otherManagers = $project->memberships()
            ->whereKeyNot($current->id)
            ->where('project_role', ProjectRole::Manager->value)
            ->exists();

        if (! $otherManagers) {
            throw ValidationException::withMessages([
                'project_role' => 'A project needs at least one manager.',
            ]);
        }
    }
}
