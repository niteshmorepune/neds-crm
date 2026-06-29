<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('workflow_type'); // agency_led | neds_led
            $table->string('platform');      // instagram | facebook | ...
            $table->string('status');
            $table->string('title');
            $table->text('copy_text')->nullable();
            $table->string('google_drive_link')->nullable();
            $table->date('publish_date')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('upload_token')->nullable()->unique();
            $table->timestamp('upload_token_expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pieces');
    }
};
