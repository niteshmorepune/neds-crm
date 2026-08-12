<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * In-app help. Renders the Markdown user guides from docs/user-guides/ so staff
 * can read role-relevant help without leaving the CRM. The guides are the single
 * source of truth (the same files also produce the PDF handouts).
 */
class HelpController extends Controller
{
    /** Slug => title. Acts as the whitelist (prevents path traversal). */
    private const GUIDES = [
        'getting-started' => 'Getting Started',
        'faq' => 'FAQ',
        'sales' => 'Sales',
        'support' => 'Support',
        'accounts' => 'Accounts',
        'manager' => 'Manager',
        'admin' => 'Admin',
        'intern' => 'Intern',
        'telecaller' => 'Telecaller',
        'client-portal' => 'Client Portal',
        'partner-portal' => 'Partner Portal',
        'integrations' => 'Integrations',
        'troubleshooting' => 'Troubleshooting',
    ];

    /** Guides every role can see, regardless of role-specific restrictions below. */
    private const OPEN_TO_ALL = ['getting-started', 'faq'];

    /**
     * Guide => the single non-admin/manager role it's written for. Admin and
     * Manager always bypass (same convention as HasRoleVisibility elsewhere in
     * this app), so they aren't listed here. Any guide not in OPEN_TO_ALL or
     * GUIDE_ROLES (manager, admin, client-portal, partner-portal, integrations,
     * troubleshooting) is Admin/Manager-only — there's no other role it's
     * written for.
     */
    private const GUIDE_ROLES = [
        'sales' => UserRole::Sales,
        'support' => UserRole::Support,
        'accounts' => UserRole::Accounts,
        'intern' => UserRole::Intern,
        'telecaller' => UserRole::Telecaller,
    ];

    private function accessible(string $guide, User $user): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        if (in_array($guide, self::OPEN_TO_ALL, true)) {
            return true;
        }

        return isset(self::GUIDE_ROLES[$guide]) && $user->hasRole(self::GUIDE_ROLES[$guide]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $guides = array_filter(
            self::GUIDES,
            fn ($slug) => $this->accessible($slug, $user),
            ARRAY_FILTER_USE_KEY,
        );

        $recommended = match ($user->role) {
            UserRole::Sales => ['getting-started', 'sales', 'faq'],
            UserRole::Support => ['getting-started', 'support', 'faq'],
            UserRole::Accounts => ['getting-started', 'accounts', 'faq'],
            UserRole::Manager => ['getting-started', 'manager', 'faq', 'partner-portal', 'integrations', 'troubleshooting'],
            UserRole::Admin => ['getting-started', 'admin', 'manager', 'faq', 'partner-portal', 'integrations', 'troubleshooting'],
            UserRole::Intern => ['getting-started', 'intern', 'faq'],
            UserRole::Telecaller => ['getting-started', 'telecaller', 'faq'],
        };

        return view('help.index', [
            'guides' => $guides,
            'recommended' => $recommended,
        ]);
    }

    public function show(Request $request, string $guide): View
    {
        abort_unless(array_key_exists($guide, self::GUIDES), 404);
        abort_unless($this->accessible($guide, $request->user()), 403);

        $path = base_path("docs/user-guides/{$guide}.md");
        abort_unless(is_file($path), 404);

        // heading_permalink assigns a plain id="slug" to every heading (no
        // visible icon — symbol is blank, insert stays 'after' since 'none'
        // suppresses the id too) so cross-guide links like
        // "accounts.md#3-recurring-invoices" actually scroll to that
        // section instead of just landing at the top of the guide.
        $environment = new Environment(['heading_permalink' => ['id_prefix' => '', 'symbol' => '']]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $html = (new MarkdownConverter($environment))->convert(file_get_contents($path))->getContent();

        // Rewire cross-guide links (e.g. sales.md) to the in-app help routes.
        $html = preg_replace_callback(
            '/href="([a-z0-9-]+)\.md(#[^"]*)?"/i',
            fn ($m) => array_key_exists($m[1], self::GUIDES)
                ? 'href="'.route('help.show', $m[1]).($m[2] ?? '').'"'
                : $m[0],
            $html,
        );

        return view('help.show', [
            'title' => self::GUIDES[$guide],
            'current' => $guide,
            'html' => $html,
            'guides' => array_filter(
                self::GUIDES,
                fn ($slug) => $this->accessible($slug, $request->user()),
                ARRAY_FILTER_USE_KEY,
            ),
        ]);
    }
}
