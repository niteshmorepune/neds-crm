<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'email', 'phone', 'notes'];

    public function contentPieces(): HasMany
    {
        return $this->hasMany(ContentPiece::class);
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'referring_partner_id');
    }
}
