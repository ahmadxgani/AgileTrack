<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link between a user account and a project, with the role inside it.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property ProjectRole $project_role
 */
#[Fillable(['project_id', 'user_id', 'project_role'])]
class ProjectMembership extends Model
{
    /** @use HasFactory<ProjectMembershipFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_role' => ProjectRole::class,
        ];
    }

    /**
     * Get the project the membership belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the member account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
