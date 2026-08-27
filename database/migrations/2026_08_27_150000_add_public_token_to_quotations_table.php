<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5 of the post-payment VA conversion pipeline (though this applies to
 * every Quotation sent through the CRM, not just VA-originated ones):
 * one-click WhatsApp send alongside the existing email send
 * (QuotationController::send()). public_token is a permanent, unguessable
 * identifier for the public quotation-view link
 * (App\Http\Controllers\QuotationPublicController) that the WhatsApp
 * template's Dynamic-URL button points to — same shape as
 * VisibilityAuditPurchase.report_token (2026_08_27_120000) and
 * ContentPiece.upload_token before it, non-expiring for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('public_token')->nullable()->unique()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
