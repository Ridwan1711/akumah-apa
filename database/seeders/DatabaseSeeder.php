<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            GradeLevelSeeder::class,
            FanSeeder::class,
            SubjectFanBackfillSeeder::class,
            LevelSubjectDefaultSeeder::class,
            AssessmentComponentSeeder::class,
            ViolationTypeSeeder::class,
            ReportCardTemplateSeeder::class,
        ]);
    }
}
