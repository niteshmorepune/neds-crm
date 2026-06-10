<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'date', 'tasks_completed', 'calls_made', 'leads_touched',
        'attendance_status', 'summary', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'tasks_completed' => 'integer',
            'calls_made' => 'integer',
            'leads_touched' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
