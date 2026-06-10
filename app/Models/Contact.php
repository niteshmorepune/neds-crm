<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'name',
        'designation',
        'phone',
        'email',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Make this contact the sole primary for its customer.
     */
    public function makePrimary(): void
    {
        static::where('customer_id', $this->customer_id)
            ->whereKeyNot($this->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        if (! $this->is_primary) {
            $this->forceFill(['is_primary' => true])->save();
        }
    }
}
