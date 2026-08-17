<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
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

    /**
     * Plain-text block formatted for pasting into WhatsApp/email — the
     * "send my daily report" ask. Built only from what's actually stored on
     * this row (no live re-query), so it renders identically for today's
     * report and any historical one.
     */
    public function formattedForSharing(): string
    {
        $attendance = $this->attendance_status
            ? AttendanceStatus::from($this->attendance_status)->label()
            : '—';

        $lines = [
            'Daily Report — '.$this->date->format('d M Y'),
            $this->user->name,
            '',
            "Tasks completed: {$this->tasks_completed}",
            "Calls made: {$this->calls_made}",
            "Leads touched: {$this->leads_touched}",
            "Attendance: {$attendance}",
            '',
            'Summary:',
            $this->summary,
        ];

        return implode("\n", $lines);
    }
}
