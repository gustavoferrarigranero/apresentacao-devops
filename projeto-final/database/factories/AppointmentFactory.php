<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $clinic = Clinic::factory();

        return [
            'clinic_id' => $clinic,
            'patient_id' => Patient::factory()->for($clinic),
            'starts_at' => fake()->dateTimeBetween('now', '+30 days'),
            'status' => 'scheduled',
            'legacy_reference' => fake()->optional()->uuid(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
