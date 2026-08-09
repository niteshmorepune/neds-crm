<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiddenDashboardWidget extends Model
{
    protected $fillable = ['user_id', 'widget_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
