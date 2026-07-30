<?php

namespace App\Livewire;

use App\Enums\AnnouncementAudience;
use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\QuarterlyAward;
use App\Models\User;
use App\Notifications\QuarterlyAwardNotification;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Admin/Manager review queue for pending Best Employee of the Quarter
 * suggestions — embedded on quarterly-awards/index.blade.php only when the
 * viewer is Admin/Manager (defense-in-depth: every mutating method also
 * re-checks via the QuarterlyAwardPolicy, since a Livewire component's
 * public methods are independently reachable network endpoints).
 *
 * The review form doubles as the override mechanism: the winner dropdown
 * defaults to the AI's pick but can be reassigned to any eligible peer, and
 * the citation textarea is pre-filled with the AI draft but editable —
 * Approve commits whatever's in the form, so "approve as-is" and "override"
 * are the same action.
 */
class QuarterlyAwardReview extends Component
{
    /** @var Collection<int, QuarterlyAward> */
    public Collection $pendingAwards;

    /** @var array<int, array{user_id: int, citation: string}> keyed by award id */
    public array $forms = [];

    public ?string $error = null;

    public function mount(Collection $pendingAwards): void
    {
        $this->pendingAwards = $pendingAwards->values();
        $this->syncForms();
    }

    public function eligiblePeersFor(int $awardId): Collection
    {
        $award = $this->pendingAwards->firstWhere('id', $awardId);

        if (! $award) {
            return collect();
        }

        $query = User::query()->where('is_active', true);
        $query = $award->isCompanyWide()
            ? $query->whereIn('role', self::rankableRoles())
            : $query->where('role', $award->department);

        return $query->orderBy('name')->get(['id', 'name']);
    }

    public function approve(int $awardId): void
    {
        $this->error = null;
        $award = $this->pendingAwards->firstWhere('id', $awardId);

        abort_unless($award !== null, 404);
        $this->authorize('review', $award);

        $selectedUserId = (int) ($this->forms[$awardId]['user_id'] ?? $award->user_id);
        $citation = trim((string) ($this->forms[$awardId]['citation'] ?? ''));

        if (! $this->isEligible($award, $selectedUserId)) {
            $this->error = 'The selected person is not eligible for this award.';

            return;
        }

        $award->update([
            'user_id' => $selectedUserId,
            'citation' => $citation !== '' ? $citation : $award->citation,
            'status' => AwardStatus::Approved,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $award->refresh();

        $announcement = Announcement::create([
            'title' => "{$award->title()} — {$award->periodLabel()}",
            'body' => $award->citation ?? "{$award->user->name} has been recognized as {$award->title()} for {$award->periodLabel()}.",
            'audience' => AnnouncementAudience::Staff,
            'is_pinned' => false,
            'starts_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $award->update(['announcement_id' => $announcement->id, 'notified_at' => now()]);
        $award->user->notify(new QuarterlyAwardNotification($award));

        $this->removeFromQueue($awardId);
        session()->flash('status', 'Award approved and announced.');
    }

    public function reject(int $awardId): void
    {
        $award = $this->pendingAwards->firstWhere('id', $awardId);

        abort_unless($award !== null, 404);
        $this->authorize('review', $award);

        $award->update([
            'status' => AwardStatus::Rejected,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->removeFromQueue($awardId);
        session()->flash('status', 'Award suggestion rejected.');
    }

    private function removeFromQueue(int $awardId): void
    {
        $this->pendingAwards = $this->pendingAwards->reject(fn ($a) => $a->id === $awardId)->values();
        unset($this->forms[$awardId]);
    }

    private function isEligible($award, int $userId): bool
    {
        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return false;
        }

        return $award->isCompanyWide()
            ? in_array($user->role->value, self::rankableRoles(), true)
            : $user->role->value === $award->department;
    }

    /**
     * @return list<string>
     */
    private static function rankableRoles(): array
    {
        return [
            UserRole::Sales->value, UserRole::Support->value, UserRole::Accounts->value,
            UserRole::Intern->value, UserRole::Telecaller->value,
        ];
    }

    private function syncForms(): void
    {
        foreach ($this->pendingAwards as $award) {
            $this->forms[$award->id] ??= [
                'user_id' => $award->user_id,
                'citation' => (string) $award->citation,
            ];
        }
    }

    public function render()
    {
        $eligiblePeers = $this->pendingAwards->mapWithKeys(
            fn ($award) => [$award->id => $this->eligiblePeersFor($award->id)]
        );

        return view('livewire.quarterly-award-review', ['eligiblePeers' => $eligiblePeers]);
    }
}
