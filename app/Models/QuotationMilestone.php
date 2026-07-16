<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id', 'title', 'percentage', 'amount', 'due_date', 'invoice_id', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'amount' => 'integer',
            'due_date' => 'date',
            'sort_order' => 'integer',
            'status' => MilestoneStatus::class,
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }

    public function isBilled(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * A suggestion, not an automatic status change (team still clicks Done
     * themselves): true once every task explicitly linked to this milestone
     * is Done, at least one is linked, and the milestone itself isn't Done yet.
     */
    public function suggestDone(): bool
    {
        return $this->status !== MilestoneStatus::Done
            && $this->tasks->isNotEmpty()
            && $this->tasks->every(fn (Task $task) => $task->status === TaskStatus::Done);
    }

    /**
     * The underlying work is done (team-marked, not auto-inferred) but this
     * milestone hasn't been invoiced yet — surfaced as a "ready to invoice" cue.
     */
    public function readyToInvoice(): bool
    {
        return $this->status === MilestoneStatus::Done && ! $this->isBilled();
    }
}
