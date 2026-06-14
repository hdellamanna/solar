<?php

namespace Database\Seeders;

use App\Models\AppMeta;
use Illuminate\Database\Seeder;

/**
 * Seeds build metadata required by the footer and AI agent slot.
 */
class AppMetaSeeder extends Seeder
{
    public function run(): void
    {
        AppMeta::set('build_version', '0.11.0');
    }
}