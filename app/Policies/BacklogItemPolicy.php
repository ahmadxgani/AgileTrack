<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\User;

/**
 * Authorization for backlog items.
 *
 * The project membership decides: managers run the backlog, team members
 * move the status of the items assigned to them, viewers only read. The
 * global administrator role overrides all of it. Archived projects and
 * archived items are read only.
 */
class BacklogItemPolicy
{
    /**
     * Determine whether the user can list the backlog of the project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $user->is_administrator
            || Project::seesEveryProject($user)
            || $this->roleOf($user, $project) !== null;
    }

    /**
     * Determine whether the user can create items in the project.
     */
    public function create(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    /**
     * Determine whether the user can change the editable fields of the item.
     */
    public function update(User $user, BacklogItem $item): bool
    {
        return ! $item->isArchived()
            && $this->canManage($user, $item->project);
    }

    /**
     * Determine whether the user can move the item across the Kanban columns.
     *
     * Project members may do this, but only for work assigned to them.
     */
    public function updateStatus(User $user, BacklogItem $item): bool
    {
        if ($item->isArchived()) {
            return false;
        }

        $project = $item->project;

        if ($this->canManage($user, $project)) {
            return true;
        }

        // An archived project is read only even for its own members.
        if ($project->isArchived()) {
            return false;
        }

        return $this->roleOf($user, $project) === ProjectRole::Member
            && $item->isAssignedTo($user);
    }

    /**
     * Determine whether the user can archive the item.
     */
    public function archive(User $user, BacklogItem $item): bool
    {
        return ! $item->isArchived()
            && $this->canManage($user, $item->project);
    }

    /**
     * Determine whether the user runs the backlog of the project.
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
