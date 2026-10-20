<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillAppointmentReferences as BackfillCommand;
use App\Console\Commands\VerifyDeploymentWorker;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Middleware\AttachReleaseContext;
use App\Jobs\BackfillAppointmentReferences;
use App\Jobs\RecordDeploymentProbe;
use App\Models\DeploymentProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeploymentStackReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_and_infrastructure_artifacts_are_ready(): void
    {
        $compose = file_get_contents(base_path('compose.yaml'));

        $this->assertStringContainsString('sail/runtimes/8.3', $compose);
        $this->assertStringContainsString('mysql:8.4', $compose);
        $this->assertStringContainsString('redis:7.4-alpine', $compose);
        $this->assertStringContainsString('queue:work', $compose);
        $this->assertStringContainsString('schedule:work', $compose);
        $this->assertFileExists(base_path('Dockerfile'));
        $this->assertFileExists(base_path('.github/workflows/ci.yml'));
        $this->assertFileExists(base_path('phpstan.neon'));
        $this->assertFileExists(base_path('scripts/deploy.sh'));
        $this->assertFileExists(base_path('scripts/rollback.sh'));
    }

    public function test_lesson_artifacts_exist_without_the_final_implementation(): void
    {
        $classes = [
            BackfillCommand::class,
            VerifyDeploymentWorker::class,
            HealthCheckController::class,
            AttachReleaseContext::class,
            BackfillAppointmentReferences::class,
            RecordDeploymentProbe::class,
            DeploymentProbe::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class));
        }

        $this->assertTrue(Schema::hasColumn('appointments', 'legacy_reference'));
        $this->assertFalse(Schema::hasColumn('appointments', 'external_reference'));
        $this->assertFalse(Schema::hasTable('deployment_probes'));
        $this->assertFileExists(database_path('contract-migrations/2026_11_01_000001_drop_legacy_reference_from_appointments_table.php'));
    }
}
