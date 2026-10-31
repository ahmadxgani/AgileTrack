<?php

namespace App\Enums;

/**
 * The lifecycle status of a project.
 *
 * Stored as a string so the schema stays portable across SQLite and
 * PostgreSQL, the same way account roles are.
 */
enum ProjectStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
        };
    }
}
