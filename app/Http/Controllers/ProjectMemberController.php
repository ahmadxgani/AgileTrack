<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\StoreProjectMemberRequest;
use App\Http\Requests\Projects\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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

        $membership->update(['project_role' => $request->string('project_role')->toString()]);

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

        $membership->delete();

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
}
