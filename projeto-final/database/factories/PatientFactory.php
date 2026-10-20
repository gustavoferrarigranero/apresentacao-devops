<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    public function definition(): array
    {
        return ['clinic_id' => Clinic::factory(), 'name' => fake()->name(), 'email' => fake()->unique()->safeEmail()];
    }
}
