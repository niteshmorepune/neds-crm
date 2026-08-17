<?php

namespace App\Enums;

/**
 * The one KRA metric each non-Sales role's target is measured against.
 * Deliberately a single fixed metric per role, mirroring SalesTarget's own
 * single-number simplicity, rather than a free-form/admin-configurable
 * metric — a bounded registry, same discipline as CrmQueryCatalog/
 * NudgeAutoDetectType elsewhere in this app. Sales itself has no case here;
 * it keeps its own pre-existing SalesTarget/deal-value mechanism unchanged.
 */
enum TargetMetric: string
{
    case TicketsResolved = 'tickets_resolved';
    case CollectionsRecorded = 'collections_recorded';
    case TasksCompleted = 'tasks_completed';
    case CallsMade = 'calls_made';

    public function label(): string
    {
        return match ($this) {
            self::TicketsResolved => 'Tickets resolved',
            self::CollectionsRecorded => 'Collections recorded',
            self::TasksCompleted => 'Tasks completed',
            self::CallsMade => 'Calls made',
        };
    }

    /** True for the one metric stored as paise rather than a plain count. */
    public function isMoney(): bool
    {
        return $this === self::CollectionsRecorded;
    }

    /**
     * The role this metric belongs to — the inverse of forRole(), used to
     * scope a Team Targets query back to "which users does this metric
     * apply to."
     */
    public function role(): UserRole
    {
        return match ($this) {
            self::TicketsResolved => UserRole::Support,
            self::CollectionsRecorded => UserRole::Accounts,
            self::TasksCompleted => UserRole::Intern,
            self::CallsMade => UserRole::Telecaller,
        };
    }

    /**
     * This role's one KRA metric, or null for a role with no generalized
     * target (Sales keeps its own SalesTarget mechanism; Admin/Manager are
     * evaluators, not participants — same distinction the Incentive module
     * and Employee Performance ranking already make).
     */
    public static function forRole(UserRole $role): ?self
    {
        return match ($role) {
            UserRole::Support => self::TicketsResolved,
            UserRole::Accounts => self::CollectionsRecorded,
            UserRole::Intern => self::TasksCompleted,
            UserRole::Telecaller => self::CallsMade,
            default => null,
        };
    }
}
