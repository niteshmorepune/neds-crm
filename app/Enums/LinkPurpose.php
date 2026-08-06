<?php

namespace App\Enums;

/**
 * Bounded purpose taxonomy for Important Links (what kind of link it is) —
 * nullable on the model, not a case here, for the same reason as
 * LinkDepartment: "no purpose set yet" is a real state, not a category.
 *
 * Redesigned 2026-08-06 around who actually uses the link and why, after
 * the original Client Portals/Vendor Login/Internal Docs/Reference set
 * turned out not to match real usage: most links here are handed TO a
 * client/lead so they can do something themselves (register for hosting,
 * a domain, a payment gateway), not something staff log into.
 */
enum LinkPurpose: string
{
    case ClientSignup = 'client_signup';
    case Scheduling = 'scheduling';
    case TeamReference = 'team_reference';
    case InternalToolAccess = 'internal_tool_access';

    public function label(): string
    {
        return match ($this) {
            self::ClientSignup => 'Client Signup Link',
            self::Scheduling => 'Scheduling Link',
            self::TeamReference => 'Team Reference',
            self::InternalToolAccess => 'Internal Tool Access',
        };
    }
}
