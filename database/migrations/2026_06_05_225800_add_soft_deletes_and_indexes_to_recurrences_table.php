<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft deletes and performance indexes to the recurrences table.
     */
    public function up(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['user_id', 'active'], 'recurrences_user_active_idx');
            $table->index('last_generated_at', 'recurrences_last_generated_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->dropIndex('recurrences_user_active_idx');
            $table->dropIndex('recurrences_last_generated_at_idx');
            $table->dropSoftDeletes();
        });
    }
};
