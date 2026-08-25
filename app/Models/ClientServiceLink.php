<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A service-specific URL on a client (Website URL/Hosting, Instagram/FB/
 * LinkedIn, GBP link, Search Console…) — free-text label, deliberately not a
 * curated per-service-type field list (see the Client Profile overhaul
 * decisions log entry). Distinct from the company-wide/general ImportantLink
 * feature on the Links tab, which this doesn't touch.
 */
class ClientServiceLink extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['customer_id', 'service_id', 'label', 'url', 'sort_order', 'created_by'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
