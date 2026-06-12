<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\View\View;
use League\CommonMark\CommonMarkConverter;

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
        'client-portal' => 'Client Portal',
    ];

    public function index(Request $request): View
    {
        $recommended = match ($request->user()->role) {
            UserRole::Sales => ['getting-started', 'sales'],
            UserRole::Support => ['getting-started', 'support'],
            UserRole::Accounts => ['getting-started', 'accounts'],
            UserRole::Manager => ['getting-started', 'manager'],
            UserRole::Admin => ['getting-started', 'admin', 'manager'],
        };

        return view('help.index', [
            'guides' => self::GUIDES,
            'recommended' => $recommended,
        ]);
    }

    public function show(string $guide): View
    {
        abort_unless(array_key_exists($guide, self::GUIDES), 404);

        $path = base_path("docs/user-guides/{$guide}.md");
        abort_unless(is_file($path), 404);

        $html = (new CommonMarkConverter)->convert(file_get_contents($path))->getContent();

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
