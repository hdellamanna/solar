<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('motion_preference', ['auto', 'reduced', 'full'])
                ->default('auto')
                ->after('use_ai_categorize');

            $table->boolean('motion_backdrop')
                ->default(true)
                ->after('motion_preference');

            $table->boolean('motion_spring')
                ->default(true)
                ->after('motion_backdrop');

            $table->boolean('motion_parallax')
                ->default(true)
                ->after('motion_spring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'motion_preference',
                'motion_backdrop',
                'motion_spring',
                'motion_parallax',
            ]);
        });
    }
};