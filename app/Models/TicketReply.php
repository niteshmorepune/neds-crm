<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'user_id', 'contact_id', 'body', 'is_internal'];

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
     * or "System".
     */
    public function authorName(): string
    {
        return $this->author?->name ?? $this->contact?->name ?? 'System';
    }

    public function isFromCustomer(): bool
    {
        return $this->contact_id !== null;
    }
}
