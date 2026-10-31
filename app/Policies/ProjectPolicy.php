<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

/**
 * Authorization for projects.
 *
 * Access combines the global account roles with the project membership row:
 * administrators and owner-level roles see everything, everyone else sees
 * only the projects they are a member of, and only managers edit them.
 */
class ProjectPolicy
{
    /**
     * Determine whether the user can list projects.
     *
     * Every signed-in account may open the list; the query is scoped to
     * the projects it may see.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the given project.
     */
    public function view(User $user, Project $project): bool
    {
        return Project::seesEveryProject($user) || $this->roleOf($user, $project) !== null;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->is_administrator
            || $user->hasRole(UserRole::Owner)
            || $user->hasRole(UserRole::ProductOwner)
            || $user->hasRole(UserRole::ProjectManager);
    }

    /**
     * Determine whether the user can update the given project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->is_administrator || $this->roleOf($user, $project) === ProjectRole::Manager;
    }

    /**
     * Determine whether the user can add, change or remove project members.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * Get the project role the user holds, without loading every member.
     */
    private function roleOf(User $user, Project $project): ?ProjectRole
    {
        return $project->memberships()
            ->where('user_id', $user->id)
            ->value('project_role');
    }
}
