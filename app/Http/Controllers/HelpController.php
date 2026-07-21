<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
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
        'sales' => 'Sales',
        'support' => 'Support',
        'accounts' => 'Accounts',
        'manager' => 'Manager',
        'admin' => 'Admin',
        'intern' => 'Intern',
        'client-portal' => 'Client Portal',
        'integrations' => 'Integrations',
        'troubleshooting' => 'Troubleshooting',
    ];

    /** Guides restricted to admin / manager only. */
    private const ADMIN_ONLY = ['troubleshooting', 'integrations'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdminOrManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $guides = $isAdminOrManager
            ? self::GUIDES
            : array_diff_key(self::GUIDES, array_flip(self::ADMIN_ONLY));

        $recommended = match ($user->role) {
            UserRole::Sales => ['getting-started', 'sales'],
            UserRole::Support => ['getting-started', 'support'],
            UserRole::Accounts => ['getting-started', 'accounts'],
            UserRole::Manager => ['getting-started', 'manager', 'integrations', 'troubleshooting'],
            UserRole::Admin => ['getting-started', 'admin', 'manager', 'integrations', 'troubleshooting'],
            UserRole::Intern => ['getting-started', 'intern'],
        };

        return view('help.index', [
            'guides' => $guides,
            'recommended' => $recommended,
        ]);
    }

    public function show(Request $request, string $guide): View
    {
        abort_unless(array_key_exists($guide, self::GUIDES), 404);

        if (in_array($guide, self::ADMIN_ONLY, true)) {
            abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);
        }

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
            'guides' => self::GUIDES,
        ]);
    }
}
