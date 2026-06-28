<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchScheduledTasks extends Command
{
    protected $signature = 'app:dispatch-scheduled-tasks
                            {--date= : Override the trigger date (YYYY-MM-DD) — for backfill or testing}';

    protected $description = 'Create recurring maintenance tasks on active projects and notify assignees via in-app bell.';

    /**
     * Task templates.
     *
     * frequency values:
     *   twice_monthly  — 1st and 15th
     *   biweekly       — 1st and 16th
     *   monthly_1      — 1st of month
     *   monthly_25     — 25th of month
     *   weekly_monday  — every Monday
     *   quarterly      — 1st of Jan, Apr, Jul, Oct
     */
    private const TEMPLATES = [
        // ── Website / Software Development ───────────────────────────────────
        [
            'title'       => 'Website backup',
            'services'    => ['Website Development', 'Software Development'],
            'frequency'   => 'twice_monthly',
            'priority'    => 'normal',
            'description' => 'Take a full backup of the client website (files + database). Verify the backup file is complete and store it securely.',
        ],
        [
            'title'       => 'Website malware / security scan',
            'services'    => ['Website Development', 'Software Development'],
            'frequency'   => 'biweekly',
            'priority'    => 'normal',
            'description' => 'Run a malware and security scan on the website. Escalate immediately if threats are found.',
        ],
        [
            'title'       => 'Website uptime & speed check',
            'services'    => ['Website Development', 'Software Development'],
            'frequency'   => 'weekly_monday',
            'priority'    => 'normal',
            'description' => 'Check uptime status and run a PageSpeed / GTmetrix test. Log the score and flag if below 70.',
        ],
        [
            'title'       => 'WordPress / CMS plugin updates',
            'services'    => ['Website Development'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Update all plugins, themes, and CMS core. Take a backup first. Test key pages after updating.',
        ],
        [
            'title'       => 'SSL certificate expiry check',
            'services'    => ['Website Development', 'Software Development'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Check SSL certificate expiry date for the client site. Initiate renewal if expiring within 30 days.',
        ],
        [
            'title'       => 'Broken link check',
            'services'    => ['Website Development', 'SEO'],
            'frequency'   => 'monthly_1',
            'priority'    => 'low',
            'description' => 'Scan the website for broken links and 404 errors. Fix or set up redirects as needed.',
        ],
        [
            'title'       => 'GA4 / analytics review',
            'services'    => ['Website Development', 'SEO'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Review traffic trends, goal completions, and top landing pages in GA4. Note any significant changes.',
        ],

        // ── SEO ──────────────────────────────────────────────────────────────
        [
            'title'       => 'Google Search Console review',
            'services'    => ['SEO'],
            'frequency'   => 'weekly_monday',
            'priority'    => 'normal',
            'description' => 'Check for crawl errors, manual actions, and index coverage issues in Google Search Console. Fix any critical issues.',
        ],
        [
            'title'       => 'Keyword ranking report',
            'services'    => ['SEO', 'GMB'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Pull the keyword ranking report and compare with the previous month. Note significant drops or gains.',
        ],

        // ── GMB ──────────────────────────────────────────────────────────────
        [
            'title'       => 'GMB profile health check',
            'services'    => ['GMB'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Verify GMB profile info is accurate (address, hours, phone, photos). Respond to any unanswered reviews. Check for suspension warnings.',
        ],

        // ── Social Media ──────────────────────────────────────────────────────
        [
            'title'       => 'Social media account health check',
            'services'    => ['Social Media'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Verify all linked social accounts are connected and active. Check for any restricted or flagged posts.',
        ],
        [
            'title'       => 'Content calendar review',
            'services'    => ['Social Media'],
            'frequency'   => 'monthly_25',
            'priority'    => 'normal',
            'description' => 'Review and confirm the content plan for next month before the brief is auto-created on the 1st. Align with the client on themes or campaigns.',
        ],

        // ── Google Ads ────────────────────────────────────────────────────────
        [
            'title'       => 'Google Ads performance review',
            'services'    => ['Google Ads'],
            'frequency'   => 'weekly_monday',
            'priority'    => 'high',
            'description' => 'Review campaign performance: budget utilisation, CPC, CTR, and conversions. Pause underperforming keywords. Flag budget overruns.',
        ],

        // ── All services (quarterly / monthly housekeeping) ───────────────────
        [
            'title'       => 'AMC contract renewal review',
            'services'    => ['SEO', 'GMB', 'Social Media', 'Google Ads', 'Website Development', 'Software Development', 'AI Automation'],
            'frequency'   => 'monthly_1',
            'priority'    => 'normal',
            'description' => 'Check if this client\'s AMC or retainer contract is due for renewal in the next 30 days. Flag to accounts if action is needed.',
        ],
        [
            'title'       => 'Client portal contacts audit',
            'services'    => ['SEO', 'GMB', 'Social Media', 'Google Ads', 'Website Development', 'Software Development', 'AI Automation'],
            'frequency'   => 'quarterly',
            'priority'    => 'low',
            'description' => 'Review contacts on the client portal. Remove access for anyone who has left the client\'s organisation.',
        ],
    ];

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today(config('app.timezone'));

        $projects = Project::query()
            ->where('status', ProjectStatus::Active)
            ->with(['service', 'owner', 'assignees'])
            ->get();

        $created = 0;

        foreach ($projects as $project) {
            $serviceName = $project->service?->name ?? '';
            $assignee    = $this->resolveAssignee($project);

            if (! $assignee) {
                continue;
            }

            foreach (self::TEMPLATES as $template) {
                if (! in_array($serviceName, $template['services'], true)) {
                    continue;
                }

                if (! $this->isDueToday($template['frequency'], $today)) {
                    continue;
                }

                // Idempotency — skip if already created for this project today.
                $exists = Task::where('project_id', $project->id)
                    ->where('title', $template['title'])
                    ->whereDate('due_date', $today->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                $task = Task::create([
                    'title'       => $template['title'],
                    'description' => $template['description'],
                    'project_id'  => $project->id,
                    'assignee_id' => $assignee->id,
                    'due_date'    => $today->toDateString(),
                    'priority'    => $template['priority'],
                    'status'      => TaskStatus::Todo->value,
                ]);

                $assignee->notify(new TaskAssigned($task));
                $created++;
            }
        }

        $this->info("Dispatched {$created} scheduled task(s) for {$today->toDateString()}.");
        return self::SUCCESS;
    }

    private function isDueToday(string $frequency, Carbon $today): bool
    {
        return match ($frequency) {
            'twice_monthly' => in_array($today->day, [1, 15], true),
            'biweekly'      => in_array($today->day, [1, 16], true),
            'monthly_1'     => $today->day === 1,
            'monthly_25'    => $today->day === 25,
            'weekly_monday' => $today->isMonday(),
            'quarterly'     => $today->day === 1 && in_array($today->month, [1, 4, 7, 10], true),
            default         => false,
        };
    }

    private function resolveAssignee(Project $project): ?User
    {
        // Prefer the team member with pivot role = 'lead'; fall back to project owner.
        $lead = $project->assignees->firstWhere('pivot.role', 'lead');
        return $lead ?? $project->owner;
    }
}
