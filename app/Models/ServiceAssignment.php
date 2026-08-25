<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One responsible employee per (customer, service) — fills the gap a
 * retainer-only service has no Project row to hang a team assignment off of.
 * Only ever DRIVES the "who's working on this" display when that service has
 * no live Project of its own; a project-backed service keeps showing its
 * existing Project team (Project.owner_id + assignees) instead, so there's
 * never two competing answers for the same service.
 */
class ServiceAssignment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['customer_id', 'service_id', 'user_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
