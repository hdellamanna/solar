<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('use_ai_categorize')->default(false)->after('password');
            $table->timestamp('last_ai_suggestion_at')->nullable()->after('use_ai_categorize');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['use_ai_categorize', 'last_ai_suggestion_at']);
        });
    }
};
