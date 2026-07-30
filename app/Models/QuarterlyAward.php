<?php

namespace App\Models;

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Best Employee of the Quarter" — one row per department winner
 * (department = a UserRole value) plus one company-wide row
 * (department = 'company') per financial-year quarter. See
 * App\Services\QuarterlyAwardGenerator for how rows are produced and
 * App\Policies\QuarterlyAwardPolicy for who can see/review one.
 */
class QuarterlyAward extends Model
{
    use HasFactory, LogsActivity;

    public const COMPANY_WIDE = 'company';

    protected $fillable = [
        'financial_year', 'quarter', 'department', 'user_id', 'score',
        'citation', 'status', 'reviewed_by', 'reviewed_at',
        'announcement_id', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'quarter' => 'integer',
            'score' => 'integer',
            'status' => AwardStatus::class,
            'reviewed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function isCompanyWide(): bool
    {
        return $this->department === self::COMPANY_WIDE;
    }

    public function departmentLabel(): string
    {
        return $this->isCompanyWide()
            ? 'Company-wide'
            : UserRole::from($this->department)->label();
    }

    public function title(): string
    {
        return $this->isCompanyWide()
            ? 'Best Employee of the Quarter'
            : "Best {$this->departmentLabel()} Performer";
    }

    public function periodLabel(): string
    {
        return "Q{$this->quarter} FY{$this->financial_year}";
    }
}
