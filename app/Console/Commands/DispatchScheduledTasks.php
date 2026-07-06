<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
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
        // ── Website Design & Development / Software Development / AMC Service ─
        // Shared across all three: an AMC Service client gets the same core
        // upkeep an active Website/Software Dev client gets bundled in.
        [
            'title' => 'Website backup',
            'services' => ['Website Design & Development', 'Software Development', 'AMC Service'],
            'frequency' => 'twice_monthly',
            'priority' => 'normal',
            'description' => 'Take a full backup of the client website (files + database). Verify the backup file is complete and store it securely.',
        ],
        [
            'title' => 'Website malware / security scan',
            'services' => ['Website Design & Development', 'Software Development', 'AMC Service'],
            'frequency' => 'biweekly',
            'priority' => 'normal',
            'description' => 'Run a malware and security scan on the website. Escalate immediately if threats are found.',
        ],
        [
            'title' => 'Website uptime & speed check',
            'services' => ['Website Design & Development', 'Software Development', 'AMC Service'],
            'frequency' => 'weekly_monday',
            'priority' => 'normal',
            'description' => "Check uptime status and run a PageSpeed / GTmetrix test. Log the score and flag if below 70. Also test the site's contact form end-to-end.",
        ],
        [
            'title' => 'WordPress / CMS / theme updates',
            'services' => ['Website Design & Development', 'AMC Service'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Update all plugins, themes, and CMS core. Take a backup first. Test key pages after updating.',
        ],
        [
            'title' => 'SSL certificate expiry check',
            'services' => ['Website Design & Development', 'Software Development', 'AMC Service'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Check SSL certificate expiry date for the client site. Initiate renewal if expiring within 30 days.',
        ],
        [
            'title' => 'Broken link check',
            'services' => ['Website Design & Development', 'SEO', 'AMC Service'],
            'frequency' => 'monthly_1',
            'priority' => 'low',
            'description' => 'Scan the website for broken links and 404 errors. Fix or set up redirects as needed.',
        ],
        [
            'title' => 'GA4 / analytics review',
            'services' => ['Website Design & Development', 'SEO'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Review traffic trends, goal completions, and top landing pages in GA4. Note any significant changes.',
        ],
        [
            'title' => 'Website health report',
            'services' => ['Website Design & Development', 'AMC Service'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Summarize this month\'s backup, security scan, uptime, and update status into a client-facing health report.',
        ],

        // ── SEO ──────────────────────────────────────────────────────────────
        [
            'title' => 'Technical SEO review',
            'services' => ['SEO'],
            'frequency' => 'weekly_monday',
            'priority' => 'normal',
            'description' => 'Google Search Console review (crawl errors, manual actions, index coverage), XML sitemap check, and Core Web Vitals review. Fix any critical issues.',
        ],
        [
            'title' => 'On-page SEO review',
            'services' => ['SEO'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Keyword ranking review, meta title/description review, content optimization, internal linking review, image ALT tag review, new page and blog optimization.',
        ],
        [
            'title' => 'Off-page SEO review',
            'services' => ['SEO'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Directory submissions, citation updates, guest posting, profile creation, social bookmarking, backlink monitoring, competitor backlink review, and toxic link review.',
        ],
        [
            'title' => 'Monthly SEO report & client review',
            'services' => ['SEO'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly SEO report (rankings vs. last month) and schedule/hold the client review meeting.',
        ],
        [
            'title' => 'Keyword ranking report',
            'services' => ['GMB'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Pull the keyword ranking report and compare with the previous month. Note significant drops or gains.',
        ],

        // ── GMB ──────────────────────────────────────────────────────────────
        [
            'title' => 'Weekly Google post',
            'services' => ['GMB'],
            'frequency' => 'weekly_monday',
            'priority' => 'normal',
            'description' => 'Publish this week\'s Google Business post. Update photos, products, or services if new ones are ready.',
        ],
        [
            'title' => 'GMB profile & engagement review',
            'services' => ['GMB'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Verify profile info is accurate (address, hours, phone, photos). Reply to unanswered reviews, answer new Q&A, review performance insights, and check local citations. Check for suspension warnings.',
        ],
        [
            'title' => 'Monthly GMB report',
            'services' => ['GMB'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly GMB performance report (views, calls, direction requests, posts published).',
        ],

        // ── Social Media ──────────────────────────────────────────────────────
        [
            'title' => 'Social media account health check',
            'services' => ['Social Media'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Verify all linked social accounts are connected and active. Check for any restricted or flagged posts.',
        ],
        [
            'title' => 'Content calendar review',
            'services' => ['Social Media'],
            'frequency' => 'monthly_25',
            'priority' => 'normal',
            'description' => 'Review and confirm the content plan for next month before the brief is auto-created on the 1st. Align with the client on themes or campaigns.',
        ],
        [
            'title' => 'Monthly social media report',
            'services' => ['Social Media'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly social media performance report (reach, engagement, follower growth, top posts).',
        ],

        // ── Performance Marketing (formerly Google Ads) ────────────────────────
        [
            'title' => 'Performance marketing campaign review',
            'services' => ['Performance Marketing'],
            'frequency' => 'weekly_monday',
            'priority' => 'high',
            'description' => 'Campaign monitoring, budget/keyword/audience optimization, ad copy testing, creative refresh, A/B testing, and conversion analysis. Pause underperforming keywords. Flag budget overruns.',
        ],
        [
            'title' => 'Monthly performance marketing report',
            'services' => ['Performance Marketing'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly ads performance report (spend, CPC, CTR, conversions vs. last month).',
        ],

        // ── Software Development ───────────────────────────────────────────────
        [
            'title' => 'Server & database health review',
            'services' => ['Software Development'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Server monitoring, database optimization, performance optimization, security patch review, and support ticket backlog review.',
        ],
        [
            'title' => 'Monthly maintenance report',
            'services' => ['Software Development'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly maintenance report (bugs fixed, features shipped, uptime, support tickets resolved).',
        ],

        // ── AI Automation ───────────────────────────────────────────────────────
        [
            'title' => 'AI automation workflow review',
            'services' => ['AI Automation'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Workflow monitoring, AI prompt optimization, error review, API health check, and automation enhancement.',
        ],
        [
            'title' => 'Monthly AI automation report',
            'services' => ['AI Automation'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly automation performance report (runs completed, errors, time saved).',
        ],

        // ── AMC Service ─────────────────────────────────────────────────────────
        [
            'title' => 'Monthly AMC report',
            'services' => ['AMC Service'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Prepare the monthly AMC health report summarizing backups, security scans, uptime, and updates performed this month.',
        ],

        // ── All services (quarterly / monthly housekeeping) ───────────────────
        [
            'title' => 'AMC contract renewal review',
            'services' => ['SEO', 'GMB', 'Social Media', 'Performance Marketing', 'Website Design & Development', 'Software Development', 'AI Automation', 'AMC Service'],
            'frequency' => 'monthly_1',
            'priority' => 'normal',
            'description' => 'Check if this client\'s AMC or retainer contract is due for renewal in the next 30 days. Flag to accounts if action is needed.',
        ],
        [
            'title' => 'Client portal contacts audit',
            'services' => ['SEO', 'GMB', 'Social Media', 'Performance Marketing', 'Website Design & Development', 'Software Development', 'AI Automation', 'AMC Service'],
            'frequency' => 'quarterly',
            'priority' => 'low',
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
            $assignee = $this->resolveAssignee($project);

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
                    'title' => $template['title'],
                    'description' => $template['description'],
                    'project_id' => $project->id,
                    'assignee_id' => $assignee->id,
                    'due_date' => $today->toDateString(),
                    'priority' => $template['priority'],
                    'status' => TaskStatus::Todo->value,
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
            'biweekly' => in_array($today->day, [1, 16], true),
            'monthly_1' => $today->day === 1,
            'monthly_25' => $today->day === 25,
            'weekly_monday' => $today->isMonday(),
            'quarterly' => $today->day === 1 && in_array($today->month, [1, 4, 7, 10], true),
            default => false,
        };
    }

    private function resolveAssignee(Project $project): ?User
    {
        // Prefer the team member with pivot role = 'lead'; fall back to project owner.
        $lead = $project->assignees->firstWhere('pivot.role', 'lead');

        return $lead ?? $project->owner;
    }
}
