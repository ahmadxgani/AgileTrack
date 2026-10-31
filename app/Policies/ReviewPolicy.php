<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\SprintReview;
use App\Models\User;

/**
 * Authorization for sprint reviews.
 *
 * Managers schedule reviews, edit them and decide them. Project members
 * read them and record the feedback text. Viewers only read. The decision
 * is closed to the customer account role, which has no external flow yet.
 * Archived projects are read only.
 */
class ReviewPolicy
{
    /**
     * Determine whether the user can list the reviews of the project.
     */
    public function viewAny(User $user, Project $project): bool
    {
        return $user->is_administrator
            || Project::seesEveryProject($user)
            || $this->roleOf($user, $project) !== null;
    }

    /**
     * Determine whether the user can read the review.
     */
    public function view(User $user, SprintReview $review): bool
    {
        return $this->viewAny($user, $review->project);
    }

    /**
     * Determine whether the user can schedule a review in the project.
     */
    public function create(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    /**
     * Determine whether the user can change the editable fields of the review.
     */
    public function update(User $user, SprintReview $review): bool
    {
        return $this->canManage($user, $review->project);
    }

    /**
     * Determine whether the user can record the feedback text of the review.
     *
     * This is the one change a project member may make; viewers only read.
     */
    public function recordFeedback(User $user, SprintReview $review): bool
    {
        $project = $review->project;

        if ($project->isArchived()) {
            return false;
        }

        if ($this->canManage($user, $project)) {
            return true;
        }

        return $this->roleOf($user, $project) === ProjectRole::Member;
    }

    /**
     * Determine whether the user can decide the review.
     *
     * The customer role cannot decide: reviews are decided internally until
     * an external customer flow exists.
     */
    public function decide(User $user, SprintReview $review): bool
    {
        return ! $user->hasRole(UserRole::Customer)
            && $this->canManage($user, $review->project);
    }

    /**
     * Determine whether the user runs the reviews of the project.
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
