<?php

namespace App\Http\Controllers;

use App\Http\Requests\FestivalRequest;
use App\Models\Festival;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/manager management of the festival calendar that drives the team
 * dashboard reminder and AI-drafted client greeting content. Only fixed-date
 * national holidays are seeded — lunar/regional festivals (Diwali, Holi,
 * Ganesh Chaturthi, Eid, Navratri, Gudi Padwa) must be added here manually
 * each year since their dates shift and can't be guessed reliably.
 */
class FestivalController extends Controller
{
    public function index(): View
    {
        return view('festivals.index', [
            'festivals' => Festival::orderBy('date')->get(),
        ]);
    }

    public function store(FestivalRequest $request): RedirectResponse
    {
        Festival::create($request->validated());

        return back()->with('status', 'Festival added.');
    }

    public function update(FestivalRequest $request, Festival $festival): RedirectResponse
    {
        $festival->update($request->validated());

        return back()->with('status', 'Festival updated.');
    }

    public function destroy(Festival $festival): RedirectResponse
    {
        $festival->delete();

        return back()->with('status', 'Festival removed.');
    }
}
