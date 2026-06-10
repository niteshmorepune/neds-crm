<?php

namespace App\Http\Middleware;

use App\Services\MenuResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces ROLE-BASED access to a route, identified by a menu key, e.g.
 * ->middleware('menu.access:invoices'). This is the real security boundary —
 * it does not consult sidebar visibility, so hiding a menu item never grants
 * access and a granted cosmetic override never bypasses a role restriction.
 */
class EnsureMenuAccess
{
    public function __construct(private readonly MenuResolver $menu) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->menu->canAccess($user, $key)) {
            abort(403);
        }

        return $next($request);
    }
}
