<?php

namespace App\Models;

use App\Enums\LinkDepartment;
use App\Enums\LinkPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportantLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'department',
        'purpose',
        'label',
        'url',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'department' => LinkDepartment::class,
            'purpose' => LinkPurpose::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Company-wide links (not attached to any client). */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('customer_id');
    }
}
