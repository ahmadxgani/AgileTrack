<?php

namespace App\Enums;

/**
 * The lifecycle of a sprint.
 *
 * Stored as a string so the column stays readable and portable across
 * SQLite and PostgreSQL.
 */
enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Closed = 'closed';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }

    /**
     * Determine whether the items of a sprint in this state may still change.
     */
    public function acceptsItemChanges(): bool
    {
        return $this !== self::Closed;
    }
}
