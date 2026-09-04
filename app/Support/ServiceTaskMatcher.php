<?php

namespace App\Support;

/**
 * Matches a Project's live service name against a recurring/onboarding task
 * template's canonical service list (DispatchScheduledTasks::TEMPLATES /
 * CreateOnboardingTasks::TEMPLATES). Service.name is admin-editable via the
 * live Service Management screen (ServiceController::update()), and every
 * rename so far has drifted from ServicesSeeder's original canonical name
 * without the task templates being updated to match.
 *
 * Real incident, 2026-09-04: 'GMB', 'Social Media', and 'AMC Service' had
 * each separately been renamed in production ('GMB Services', 'Social Media
 * Management', 'Annual Maintenance Services'), silently breaking every
 * `in_array($serviceName, $template['services'], true)` check across both
 * files for those three service lines — recurring maintenance tasks and
 * one-time onboarding checklists had stopped being auto-created for every
 * GMB/Social Media/AMC client, invisibly, since whichever rename happened
 * first. Same root cause as VisibilityAuditFunnelMetrics::gmbServiceId()'s
 * own fix the same day.
 *
 * Service.slug is NOT a stable identifier either — ServiceController::
 * update() recomputes it from the new name on every edit, so it drifts in
 * lockstep with name. Service.id is the only truly stable identifier, but
 * the templates list human-readable names for maintainability — this class
 * is the one place a rename needs a new alias added, instead of touching
 * every template row that references it (22 rows in DispatchScheduledTasks
 * alone).
 */
class ServiceTaskMatcher
{
    /**
     * Canonical name (as written in every template's 'services' array) =>
     * every real name that service has ever been known by in production,
     * oldest first. Add a new entry here (not a template-array edit) the
     * next time a service gets renamed.
     */
    private const ALIASES = [
        'GMB' => ['GMB', 'GMB Services'],
        'Social Media' => ['Social Media', 'Social Media Management'],
        'AMC Service' => ['AMC Service', 'Annual Maintenance Services'],
    ];

    /**
     * @param  string  $projectServiceName  $project->service?->name ?? ''
     * @param  list<string>  $templateServices  a template's 'services' array (canonical names)
     */
    public static function matches(string $projectServiceName, array $templateServices): bool
    {
        if ($projectServiceName === '') {
            return false;
        }

        foreach ($templateServices as $canonicalName) {
            $knownNames = self::ALIASES[$canonicalName] ?? [$canonicalName];

            if (in_array($projectServiceName, $knownNames, true)) {
                return true;
            }
        }

        return false;
    }
}
