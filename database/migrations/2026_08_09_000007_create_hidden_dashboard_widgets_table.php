<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard Customization (Manager panel doc Tier 3, scoped down to
 * show/hide only, no reorder/drag — confirmed via AskUserQuestion). Only
 * stores the OVERRIDE: every widget is visible by default for everyone, so
 * a row here means "this user hid this widget" — no default/role variance
 * to track, unlike menu_item_user (which layers a per-user override on top
 * of a real per-role default). widget_key is validated against
 * App\Support\DashboardWidgets' bounded catalog before being written, never
 * free text from the request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hidden_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('widget_key');
            $table->timestamps();

            $table->unique(['user_id', 'widget_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_dashboard_widgets');
    }
};
