<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Nullable: a Business Documents/Other-category asset (a signed
            // MSA, a GST certificate) is often client-wide, not tied to one
            // service — unlike client_service_links, which is always
            // per-service by definition.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('title');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // The CURRENT version number — bumped on every "Replace/Upload
            // New Version"; the superseded file's row moves to
            // client_asset_versions before this row's file fields are
            // overwritten. See ClientAsset::replaceFile().
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['customer_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_assets');
    }
};
