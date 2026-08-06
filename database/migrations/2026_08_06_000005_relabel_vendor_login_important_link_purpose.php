<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * LinkPurpose::VendorLogin ('vendor_login') no longer exists — the real
     * links using it (Hostinger, Razorpay, PhonePe signup/referral links)
     * are handed to clients to register themselves, not staff logins, so
     * they belong under the new ClientSignup ('client_signup') case
     * instead. The other three retired cases (client_portal, internal_docs,
     * reference) have zero rows in production as of this migration, so no
     * data movement is needed for them.
     */
    public function up(): void
    {
        DB::table('important_links')
            ->where('purpose', 'vendor_login')
            ->update(['purpose' => 'client_signup']);
    }

    public function down(): void
    {
        DB::table('important_links')
            ->where('purpose', 'client_signup')
            ->update(['purpose' => 'vendor_login']);
    }
};
