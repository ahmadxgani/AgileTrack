<?php

namespace App\Enums;

/**
 * The work status of a backlog item, the first Kanban columns.
 *
 * Stored as a string so the column set stays configurable later without a
 * schema change, and so the schema stays portable across SQLite and
 * PostgreSQL.
 */
enum BacklogStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::Done => 'Done',
        };
    }
}
