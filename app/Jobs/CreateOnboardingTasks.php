<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\TaskAssigned;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Creates the one-time onboarding checklist for a newly created project,
 * matched by service, assigned to the project's lead (or owner as
 * fallback). Dispatched from Project::booted()'s created hook — by the time
 * a queued job actually runs, assignees()->sync() (called right after
 * Project::create() in both ProjectController::store() and
 * CreateProjectFromDeal) will already have committed, so re-querying the
 * project fresh here sees the real assignee.
 *
 * Idempotent by title — a duplicate dispatch (e.g. a retried job) won't
 * create the same onboarding task twice for a project.
 */
class CreateOnboardingTasks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One-time setup checklists, consolidated per project phase (not one
     * task per line item — this fires once per project, not monthly, but
     * keeping the same "checklist in the description" shape as the
     * recurring templates keeps the pattern consistent).
     */
    private const TEMPLATES = [
        // ── SEO ──────────────────────────────────────────────────────────────
        [
            'title' => 'Technical SEO setup',
            'services' => ['SEO'],
            'due_in_days' => 5,
            'priority' => 'high',
            'description' => 'Website SEO audit, Google Search Console setup, GA4 setup, GTM setup, Bing Webmaster Tools setup, XML sitemap submission, robots.txt review, SSL verification, canonical tag setup, schema markup setup, crawl/404 error fix, redirect review, Core Web Vitals review, page speed optimization, mobile-friendly check, indexing review.',
        ],
        [
            'title' => 'On-page SEO setup',
            'services' => ['SEO'],
            'due_in_days' => 10,
            'priority' => 'normal',
            'description' => 'Keyword research, keyword mapping, competitor analysis, meta title/description optimization, URL optimization, heading tags optimization, image ALT tags, internal linking setup, content optimization, FAQ optimization, breadcrumb setup.',
        ],
        [
            'title' => 'Off-page SEO setup',
            'services' => ['SEO'],
            'due_in_days' => 14,
            'priority' => 'normal',
            'description' => 'Backlink audit, competitor backlink analysis, directory submission setup, Google Business Profile linking, initial citation creation.',
        ],
        [
            'title' => 'Initial SEO report',
            'services' => ['SEO'],
            'due_in_days' => 15,
            'priority' => 'normal',
            'description' => 'Prepare and send the initial SEO baseline report to the client.',
        ],

        // ── GMB ──────────────────────────────────────────────────────────────
        [
            'title' => 'GMB profile setup',
            'services' => ['GMB'],
            'due_in_days' => 7,
            'priority' => 'high',
            'description' => 'Google Business Profile audit, business information setup, category selection, service/product addition, business description update, photo + cover photo upload, Q&A setup, initial review strategy.',
        ],

        // ── Website Design & Development ──────────────────────────────────────
        [
            'title' => 'Website discovery & design',
            'services' => ['Website Design & Development'],
            'due_in_days' => 10,
            'priority' => 'high',
            'description' => 'Requirement gathering, sitemap approval, wireframe approval, UI design.',
        ],
        [
            'title' => 'Website development',
            'services' => ['Website Design & Development'],
            'due_in_days' => 21,
            'priority' => 'normal',
            'description' => 'Homepage development, inner page development, contact form setup, WhatsApp integration, mobile responsive design, speed optimization, basic SEO setup.',
        ],
        [
            'title' => 'Website QA & launch',
            'services' => ['Website Design & Development'],
            'due_in_days' => 28,
            'priority' => 'normal',
            'description' => 'Browser testing, client review, bug fixes, website go-live.',
        ],

        // ── Social Media ──────────────────────────────────────────────────────
        [
            'title' => 'Social media strategy setup',
            'services' => ['Social Media'],
            'due_in_days' => 7,
            'priority' => 'high',
            'description' => 'Social media audit, competitor analysis, content strategy, content calendar, hashtag research, profile optimization, cover & profile design.',
        ],

        // ── Performance Marketing ───────────────────────────────────────────────
        [
            'title' => 'Campaign setup',
            'services' => ['Performance Marketing'],
            'due_in_days' => 7,
            'priority' => 'high',
            'description' => 'Business goal discussion, audience research, pixel setup, conversion tracking, campaign setup, ad copy creation, creative design, campaign launch.',
        ],

        // ── Software Development ───────────────────────────────────────────────
        [
            'title' => 'Requirements & design',
            'services' => ['Software Development'],
            'due_in_days' => 14,
            'priority' => 'high',
            'description' => 'Requirement gathering, SRS preparation, database design, UI design.',
        ],
        [
            'title' => 'Development & testing',
            'services' => ['Software Development'],
            'due_in_days' => 30,
            'priority' => 'normal',
            'description' => 'Module development, API development, testing, UAT.',
        ],
        [
            'title' => 'Deployment & training',
            'services' => ['Software Development'],
            'due_in_days' => 35,
            'priority' => 'normal',
            'description' => 'Production deployment, user training.',
        ],

        // ── AI Automation ───────────────────────────────────────────────────────
        [
            'title' => 'Automation setup',
            'services' => ['AI Automation'],
            'due_in_days' => 14,
            'priority' => 'high',
            'description' => 'Requirement analysis, workflow design, AI prompt design, API integration, automation setup, testing, training, go-live.',
        ],

        // ── AMC Service ─────────────────────────────────────────────────────────
        [
            'title' => 'AMC onboarding audit',
            'services' => ['AMC Service'],
            'due_in_days' => 7,
            'priority' => 'high',
            'description' => 'AMC agreement, website audit, server audit, security audit, backup configuration, maintenance plan.',
        ],
    ];

    public function __construct(public int $projectId) {}

    public function handle(): void
    {
        $project = Project::with(['service', 'owner', 'assignees'])->find($this->projectId);

        if ($project === null) {
            return;
        }

        $serviceName = $project->service?->name ?? '';
        $assignee = $project->assignees->firstWhere('pivot.role', 'lead') ?? $project->owner;

        if (! $assignee) {
            return;
        }

        foreach (self::TEMPLATES as $template) {
            if (! in_array($serviceName, $template['services'], true)) {
                continue;
            }

            $exists = Task::where('project_id', $project->id)
                ->where('title', $template['title'])
                ->exists();

            if ($exists) {
                continue;
            }

            $task = Task::create([
                'title' => $template['title'],
                'description' => $template['description'],
                'project_id' => $project->id,
                'assignee_id' => $assignee->id,
                'due_date' => now()->addDays($template['due_in_days'])->toDateString(),
                'priority' => $template['priority'],
                'status' => TaskStatus::Todo->value,
            ]);

            $assignee->notify(new TaskAssigned($task));
        }
    }
}
