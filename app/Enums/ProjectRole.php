<?php

namespace App\Enums;

/**
 * The role a user holds inside a single project.
 *
 * Separate from the global account roles in UserRole: a global team member
 * can manage one project and only watch another.
 */
enum ProjectRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Get the human readable label for the project role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Member => 'Member',
            self::Viewer => 'Viewer',
        };
    }
}
