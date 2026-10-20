<?php

namespace Tests\Feature;

use App\Models\DeploymentProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_available(): void
    {
        DeploymentProbe::query()->create([
            'token' => fake()->uuid(),
            'release' => 'test',
            'processed_at' => now(),
        ]);

        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_clinicflow_tables_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('clinics'));
        $this->assertTrue(Schema::hasTable('patients'));
        $this->assertTrue(Schema::hasTable('appointments'));
    }
}
