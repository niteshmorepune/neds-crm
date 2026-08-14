<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read bool $is_internal
 */
class TicketReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'user_id', 'contact_id', 'body', 'is_internal',
        'whatsapp_direction', 'external_sender_name',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Display name for the reply author — an internal user, a portal contact,
     * a WhatsApp-only sender who has neither (see external_sender_name), or
     * "System".
     */
    public function authorName(): string
    {
        return $this->author?->name ?? $this->external_sender_name ?? $this->contact?->name ?? 'System';
    }

    /**
     * True for a portal-contact reply OR an inbound WhatsApp message from
     * the actual customer (whatsapp_direction='inbound') — the latter has no
     * portal Contact row, just the phone number's contact name, captured in
     * external_sender_name instead.
     */
    public function isFromCustomer(): bool
    {
        return $this->contact_id !== null || $this->whatsapp_direction === 'inbound';
    }
}
