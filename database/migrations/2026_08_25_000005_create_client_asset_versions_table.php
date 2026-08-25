<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archived, immutable copies of a ClientAsset's prior files — created
        // once, on replace, and never edited afterward. No precedent for file
        // versioning existed anywhere in this codebase before this table;
        // every other attachment flow (Attachment, TeamResource) is purely
        // additive/replace-in-place with no history kept.
        Schema::create('client_asset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_asset_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_asset_versions');
    }
};
