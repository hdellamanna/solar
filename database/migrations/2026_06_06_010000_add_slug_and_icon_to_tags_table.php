<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to the tags table created by the 3A sub-agent.
        Schema::table('tags', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->string('icon')->nullable()->after('color');
            // Composite unique on (user_id, slug) when not null. SQLite/Postgres/MySQL all support.
            $table->unique(['user_id', 'slug'], 'tags_user_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_user_slug_unique');
            $table->dropColumn(['slug', 'icon']);
        });
    }
};
