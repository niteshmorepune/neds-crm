<?php

use App\Enums\QuotationApprovalStatus;
use App\Enums\QuotationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central Approval Center (Manager panel doc "clarify first" item, scoped
 * via AskUserQuestion): Quotations get a real approval gate for the first
 * time — a Draft quotation cannot be sent to a client until an Admin/
 * Manager approves it. Deliberately a NEW, separate field from the
 * existing `status` column: QuotationStatus::Rejected already means "the
 * client received it and said no" — approval_status tracks an internal
 * manager review that happens before the client ever sees it, and
 * reusing the same enum value for both would conflate two different
 * events.
 *
 * Backfill: only genuinely un-sent Draft quotations are left 'pending'
 * (they'll need one approval click before they can be sent under the new
 * rule). Anything that already reached Sent/Accepted/Rejected evidently
 * WAS already sent under the old rules — retroactively gating those would
 * serve no purpose, so they're backfilled straight to 'approved'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('approval_status')->default(QuotationApprovalStatus::Pending->value)->after('status');
            $table->text('approval_notes')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->after('approval_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        DB::table('quotations')
            ->where('status', '!=', QuotationStatus::Draft->value)
            ->update(['approval_status' => QuotationApprovalStatus::Approved->value]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approval_notes', 'approved_at']);
        });
    }
};
