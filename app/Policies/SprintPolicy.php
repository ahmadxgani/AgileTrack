<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;

/**
 * Authorization for sprints.
 *
 * Managers plan sprints and swap their items; every project member may
 * read them. The global administrator role overrides all of it. Archived
 * projects and closed sprints are read only.
 */
class SprintPolicy
{
    /**
     * Determine whether the user can list the sprints of the project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $user->is_administrator
            || Project::seesEveryProject($user)
            || $this->roleOf($user, $project) !== null;
    }

    /**
     * Determine whether the user can create sprints in the project.
     */
    public function create(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    /**
     * Determine whether the user can change the editable fields of the sprint.
     */
    public function update(User $user, Sprint $sprint): bool
    {
        return ! $sprint->isClosed()
            && $this->canManage($user, $sprint->project);
    }

    /**
     * Determine whether the user can start the sprint.
     *
     * A closed sprint cannot be restarted; the one-active rule itself is
     * enforced by the controller, which can see the sibling sprints.
     */
    public function start(User $user, Sprint $sprint): bool
    {
        return ! $sprint->isClosed() && $this->canManage($user, $sprint->project);
    }

    /**
     * Determine whether the user can close the sprint.
     */
    public function close(User $user, Sprint $sprint): bool
    {
        return ! $sprint->isClosed() && $this->canManage($user, $sprint->project);
    }

    /**
     * Determine whether the user can add items to or remove them from the sprint.
     *
     * Whether the sprint still accepts changes is a state rule, so it is
     * checked by the controller and reported as a validation error.
     */
    public function manageItems(User $user, Sprint $sprint): bool
    {
        return $this->canManage($user, $sprint->project);
    }

    /**
     * Determine whether the user runs the sprints of the project.
     */
    private function canManage(User $user, Project $project): bool
    {
        if ($project->isArchived()) {
            return false;
        }

        return $user->is_administrator
            || $this->roleOf($user, $project) === ProjectRole::Manager;
    }

    /**
     * Get the project role the user holds, reusing the loaded relation.
     */
    private function roleOf(User $user, Project $project): ?ProjectRole
    {
        if (! $project->relationLoaded('memberships')) {
            $project->load('memberships');
        }

        return $project->memberships->firstWhere('user_id', $user->id)?->project_role;
    }
}
