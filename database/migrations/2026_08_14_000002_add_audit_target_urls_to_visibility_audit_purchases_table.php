<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->string('gbp_url')->nullable()->after('payer_email');
            $table->string('website_url')->nullable()->after('gbp_url');
        });
    }

    public function down(): void
    {
        Schema::table('visibility_audit_purchases', function (Blueprint $table) {
            $table->dropColumn(['gbp_url', 'website_url']);
        });
    }
};
