<?php

namespace App\Enums;

/**
 * The account roles defined by the SRS.
 *
 * Roles are stored as strings in the `user_roles` table so the schema stays
 * portable across SQLite and PostgreSQL.
 */
enum UserRole: string
{
    case Administrator = 'administrator';
    case Owner = 'owner';
    case ProductOwner = 'product_owner';
    case ProjectManager = 'project_manager';
    case BusinessAnalyst = 'business_analyst';
    case TeamMember = 'team_member';
    case QaEngineer = 'qa_engineer';
    case Customer = 'customer';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Owner => 'Owner / Sponsor',
            self::ProductOwner => 'Product Owner',
            self::ProjectManager => 'Project Manager',
            self::BusinessAnalyst => 'Business Analyst',
            self::TeamMember => 'Team Member',
            self::QaEngineer => 'QA Engineer',
            self::Customer => 'Customer',
        };
    }
}
