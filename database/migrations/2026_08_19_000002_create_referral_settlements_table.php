<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (RecurringInvoice, month) tracking the referral share settled
 * between NEDS and a Partner for that service that month — a genuinely new
 * concept, distinct from partner_commission_statements (one-time, per-Partner,
 * Deal.value at Won) and from a reseller's billing_customer_id (consolidated
 * invoicing, not a share split). One symmetric table covers BOTH money-flow
 * directions: flow_mode is snapshotted per row (so a client that later
 * switches mode doesn't corrupt history), and owesDirection() on the model
 * derives who owes whom from it.
 *
 * recurring_invoice_id is required on every row, both flow modes — settlement
 * is always tracked per service, matching the owner's real manual Excel sheet.
 *
 * amount_source distinguishes how billed_amount was determined:
 * - 'invoice' (NedsCollects): auto-summed from real Invoice rows by
 *   App\Console\Commands\FinalizeReferralSettlements.
 * - 'manual' (PartnerCollects): staff-entered — there is no NEDS invoice at
 *   all for these clients (owner's explicit call), so this figure IS the
 *   source of truth, not a derived/reconciled one.
 *
 * settled_at/settled_by mirror partner_commission_statements' paid_at/paid_by
 * but are named direction-neutral ("settled", not "paid") since this same
 * column pair means "NEDS paid the Partner" on a NedsCollects row and
 * "Partner remitted NEDS" on a PartnerCollects row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_invoice_id')->constrained()->cascadeOnDelete();
            $table->date('period_start'); // always the 1st of the month
            $table->string('flow_mode'); // snapshot of PartnerCollectionMode at finalize time
            $table->unsignedBigInteger('billed_amount'); // paise
            $table->decimal('share_rate', 5, 2); // snapshot, % at finalize time
            $table->unsignedBigInteger('share_amount'); // paise, billed_amount * share_rate / 100
            $table->string('amount_source'); // App\Enums\SettlementAmountSource
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['recurring_invoice_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settlements');
    }
};
