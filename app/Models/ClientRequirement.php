<?php

namespace App\Models;

use App\Enums\DeliverableStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-client-service requirement tracking — the client-level sibling of
 * ProjectDeliverable, for retainer services with no Project to hang a
 * checklist off of. A received file lives in ClientAsset (via
 * client_asset_id), never a second, separate attachment.
 */
class ClientRequirement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'service_id', 'title', 'instructions', 'status',
        'requested_date', 'due_date', 'received_date',
        'responsible_user_id', 'client_asset_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
            'requested_date' => 'date',
            'due_date' => 'date',
            'received_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function clientAsset(): BelongsTo
    {
        return $this->belongsTo(ClientAsset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
