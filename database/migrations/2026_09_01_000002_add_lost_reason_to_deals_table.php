<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Required at the point of moving a Deal to Lost (see
        // Deal::moveToStage()) -- nullable at the schema level since it
        // never applies to any other stage, and doesn't retroactively
        // apply to deals already Lost before this shipped.
        Schema::table('deals', function (Blueprint $table) {
            $table->string('lost_reason')->nullable()->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('lost_reason');
        });
    }
};
