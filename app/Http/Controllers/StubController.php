<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placeholder controller for Milestone 0. Every sidebar feature points here
 * until its real module is built in a later milestone. The page title is taken
 * from the matching menu item so the stub reflects the sidebar label (e.g.
 * "Clients" rather than the key "customer").
 */
class StubController extends Controller
{
    public function __invoke(Request $request): View
    {
        $key = $request->route()->getName();

        $label = MenuItem::query()->where('key', $key)->value('label') ?? ucfirst($key);

        return view('stub', [
            'title' => $label,
        ]);
    }
}
