<?php

namespace App\Enums;

/**
 * The outcome of a sprint review.
 *
 * Stored as a string so the column stays readable and portable across
 * SQLite and PostgreSQL.
 */
enum ReviewDecision: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case NeedsChanges = 'needs_changes';

    /**
     * Get the human readable label for the decision.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::NeedsChanges => 'Needs changes',
        };
    }

    /**
     * Determine whether this decision names the acting user and time.
     */
    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}
