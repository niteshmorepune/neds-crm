<?php

namespace App\Http\Controllers;

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Http\Requests\RegenerateQuarterlyAwardRequest;
use App\Models\QuarterlyAward;
use App\Services\QuarterlyAwardGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Best Employee of the Quarter — Admin/Manager see the full review queue
 * (App\Livewire\QuarterlyAwardReview handles approve/override/reject);
 * everyone else sees only their own past Approved awards.
 */
class QuarterlyAwardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuarterlyAward::class);

        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        $awards = QuarterlyAward::query()
            ->with(['user', 'reviewedBy'])
            ->unless(
                $isManager,
                fn ($q) => $q->where('user_id', $user->id)->where('status', AwardStatus::Approved)
            )
            ->orderByDesc('financial_year')
            ->orderByDesc('quarter')
            ->orderBy('department')
            ->get();

        return view('quarterly-awards.index', [
            'awards' => $awards,
            'isManager' => $isManager,
        ]);
    }

    public function regenerate(RegenerateQuarterlyAwardRequest $request, QuarterlyAwardGenerator $generator): RedirectResponse
    {
        $this->authorize('regenerate', QuarterlyAward::class);

        $data = $request->validated();
        $generator->generate($data['financial_year'], (int) $data['quarter']);

        return redirect()->route('quarterly-awards.index')->with('status', 'Awards regenerated for that quarter.');
    }

    public function certificate(QuarterlyAward $award): Response
    {
        $this->authorize('downloadCertificate', $award);

        $award->load('user');

        $pdf = Pdf::loadView('quarterly-awards.certificate', ['award' => $award])->setPaper('a4', 'landscape');

        $filename = str_replace(['/', ' '], '-', "{$award->title()}-{$award->periodLabel()}").'.pdf';

        return $pdf->stream($filename);
    }
}
