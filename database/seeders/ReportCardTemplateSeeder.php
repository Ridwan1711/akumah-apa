<?php

namespace Database\Seeders;

use App\Models\ReportCardTemplate;
use Illuminate\Database\Seeder;

class ReportCardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ReportCardTemplate::updateOrCreate(
            ['name' => 'Default'],
            [
                'is_default' => true,
                'config' => ReportCardTemplate::defaultConfig(),
            ]
        );
    }
}
