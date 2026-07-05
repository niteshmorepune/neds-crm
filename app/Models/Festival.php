<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Festival extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'date', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function contentPieces(): HasMany
    {
        return $this->hasMany(ContentPiece::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcomingWithin(Builder $query, int $days): Builder
    {
        return $query->whereBetween('date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function daysUntil(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->date, false);
    }
}
