<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DeploymentProbe;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Clinic::factory()->count(2)->create()->each(function (Clinic $clinic) {
            Patient::factory()->count(8)->for($clinic)->create()->each(function (Patient $patient) use ($clinic) {
                Appointment::factory()->count(3)->for($clinic)->for($patient)->create();
            });
        });

        DeploymentProbe::query()->create([
            'token' => fake()->uuid(),
            'release' => (string) config('app.release'),
            'processed_at' => now(),
        ]);
    }
}
