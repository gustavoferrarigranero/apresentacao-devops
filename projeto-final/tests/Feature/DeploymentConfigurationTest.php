<?php

namespace Tests\Feature;

use App\Jobs\BackfillAppointmentReferences;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DeploymentProbe;
use App\Models\Patient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeploymentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_builds_and_validates_an_immutable_artifact(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/ci.yml'));

        $this->assertStringContainsString('composer validate --strict', $workflow);
        $this->assertStringContainsString('php artisan test --parallel', $workflow);
        $this->assertStringContainsString('phpstan analyse', $workflow);
        $this->assertStringContainsString('pint --test', $workflow);
        $this->assertStringContainsString('npm ci', $workflow);
        $this->assertStringContainsString('npm run build', $workflow);
        $this->assertStringContainsString('pull_request:', $workflow);
        $this->assertStringContainsString('branches: [main]', $workflow);
        $this->assertStringContainsString('clinicflow-${GITHUB_SHA}.tar.gz', $workflow);
        $this->assertStringContainsString('actions/upload-artifact@v4', $workflow);
    }

    public function test_deploy_and_rollback_scripts_are_valid_and_cover_recovery(): void
    {
        $deployPath = base_path('scripts/deploy.sh');
        $rollbackPath = base_path('scripts/rollback.sh');
        $deploy = file_get_contents($deployPath);
        $rollback = file_get_contents($rollbackPath);

        (new Process(['sh', '-n', $deployPath]))->mustRun();
        (new Process(['sh', '-n', $rollbackPath]))->mustRun();

        $this->assertStringContainsString('migrate --force', $deploy);
        $this->assertStringContainsString('queue:restart', $deploy);
        $this->assertStringContainsString('schedule:interrupt', $deploy);
        $this->assertStringContainsString('horizon:terminate', $deploy);
        $this->assertStringContainsString('deployment:heartbeat --wait=30', $deploy);
        $this->assertStringContainsString('curl --fail', $deploy);
        $this->assertStringContainsString('rollback', $deploy);
        $this->assertStringContainsString('TARGET_RELEASE', $rollback);
        $this->assertStringContainsString('validate_release', $deploy);
        $this->assertStringContainsString('validate_target', $rollback);
        $this->assertStringContainsString('recover', $rollback);
    }

    public function test_failed_release_validation_restores_the_previous_release(): void
    {
        $filesystem = new Filesystem;
        $root = sys_get_temp_dir().'/clinicflow-deploy-'.Str::uuid();
        $bin = $root.'/bin';
        $oldRelease = $root.'/releases/old';
        $newRelease = $root.'/releases/new';

        try {
            foreach ([$bin, $oldRelease, $newRelease.'/vendor', $newRelease.'/public/build', $root.'/shared/storage'] as $directory) {
                $filesystem->ensureDirectoryExists($directory);
            }

            file_put_contents($newRelease.'/vendor/autoload.php', '<?php');
            file_put_contents($newRelease.'/public/build/manifest.json', '{}');
            file_put_contents($root.'/shared/.env', 'APP_ENV=testing');
            symlink($oldRelease, $root.'/current');

            $this->writeFailingRuntimeStubs($bin);

            $process = new Process([base_path('scripts/deploy.sh')], base_path(), [
                'PATH' => $bin.PATH_SEPARATOR.getenv('PATH'),
                'DEPLOY_PATH' => $root,
                'RELEASE_ID' => 'new',
                'HEALTHCHECK_URL' => 'http://127.0.0.1/health',
            ]);
            $process->run();

            $this->assertNotSame(0, $process->getExitCode());
            $this->assertSame($oldRelease, readlink($root.'/current'));
        } finally {
            $filesystem->deleteDirectory($root);
        }
    }

    public function test_failed_rollback_validation_recovers_the_active_release(): void
    {
        $filesystem = new Filesystem;
        $root = sys_get_temp_dir().'/clinicflow-rollback-'.Str::uuid();
        $bin = $root.'/bin';
        $activeRelease = $root.'/releases/active';
        $targetRelease = $root.'/releases/target';

        try {
            foreach ([$bin, $activeRelease, $targetRelease.'/vendor', $targetRelease.'/public/build'] as $directory) {
                $filesystem->ensureDirectoryExists($directory);
            }

            file_put_contents($targetRelease.'/vendor/autoload.php', '<?php');
            file_put_contents($targetRelease.'/public/build/manifest.json', '{}');
            symlink($activeRelease, $root.'/current');

            $this->writeFailingRuntimeStubs($bin);

            $process = new Process([base_path('scripts/rollback.sh')], base_path(), [
                'PATH' => $bin.PATH_SEPARATOR.getenv('PATH'),
                'DEPLOY_PATH' => $root,
                'TARGET_RELEASE' => 'target',
                'HEALTHCHECK_URL' => 'http://127.0.0.1/health',
            ]);
            $process->run();

            $this->assertNotSame(0, $process->getExitCode());
            $this->assertSame($activeRelease, readlink($root.'/current'));
        } finally {
            $filesystem->deleteDirectory($root);
        }
    }

    public function test_sail_stack_has_pinned_runtime_and_long_running_processes(): void
    {
        $compose = file_get_contents(base_path('compose.yaml'));

        $this->assertStringContainsString('sail/runtimes/8.3', $compose);
        $this->assertStringContainsString('mysql:8.4', $compose);
        $this->assertStringContainsString('redis:7.4-alpine', $compose);
        $this->assertStringContainsString('queue:work', $compose);
        $this->assertStringContainsString('schedule:work', $compose);
    }

    public function test_production_image_has_assets_non_root_user_and_healthcheck(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString('FROM node:22-alpine AS assets', $dockerfile);
        $this->assertStringContainsString('FROM php:8.3-cli-alpine', $dockerfile);
        $this->assertStringContainsString('USER clinicflow', $dockerfile);
        $this->assertStringContainsString('HEALTHCHECK', $dockerfile);
    }

    public function test_expand_migration_keeps_legacy_and_new_columns_together(): void
    {
        $this->assertTrue(Schema::hasColumn('appointments', 'legacy_reference'));
        $this->assertTrue(Schema::hasColumn('appointments', 'external_reference'));
        $this->assertFileExists(database_path('contract-migrations/2026_11_01_000001_drop_legacy_reference_from_appointments_table.php'));
    }

    public function test_backfill_job_is_idempotent(): void
    {
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->for($clinic)->create();
        $appointments = Appointment::factory()->count(3)->for($clinic)->for($patient)->create([
            'external_reference' => null,
        ]);
        $job = new BackfillAppointmentReferences($appointments->modelKeys());

        $job->handle();
        $references = Appointment::query()->orderBy('id')->pluck('external_reference')->all();
        $job->handle();

        $this->assertSame($references, Appointment::query()->orderBy('id')->pluck('external_reference')->all());
        $this->assertNotContains(null, $references);
    }

    public function test_backfill_command_dispatches_bounded_jobs(): void
    {
        Queue::fake();
        $clinic = Clinic::factory()->create();
        $patient = Patient::factory()->for($clinic)->create();
        Appointment::factory()->count(5)->for($clinic)->for($patient)->create(['external_reference' => null]);

        $this->artisan('appointments:backfill-references --chunk=2')->assertSuccessful();

        Queue::assertPushed(BackfillAppointmentReferences::class, 3);
    }

    public function test_readiness_health_checks_database_cache_worker_and_release(): void
    {
        DeploymentProbe::query()->create([
            'token' => fake()->uuid(),
            'release' => 'test-release',
            'processed_at' => now(),
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-App-Release', 'local')
            ->assertJson([
                'status' => 'ok',
                'release' => 'local',
                'checks' => ['database' => true, 'cache' => true, 'worker' => true],
            ]);
    }

    public function test_health_check_reports_a_stale_worker(): void
    {
        DeploymentProbe::query()->create([
            'token' => fake()->uuid(),
            'release' => 'old-release',
            'processed_at' => now()->subMinutes(10),
        ]);

        $this->getJson('/api/health')
            ->assertServiceUnavailable()
            ->assertJsonPath('checks.worker', false);
    }

    public function test_worker_probe_command_runs_through_the_queue(): void
    {
        $this->artisan('deployment:heartbeat --wait=2')->assertSuccessful();

        $this->assertDatabaseCount('deployment_probes', 1);
        $this->assertNotNull(DeploymentProbe::first()->processed_at);
    }

    public function test_scheduler_monitors_queue_and_worker(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command');

        $this->assertTrue($commands->contains(fn (?string $command): bool => str_contains($command ?? '', 'deployment:heartbeat')));
        $this->assertTrue($commands->contains(fn (?string $command): bool => str_contains($command ?? '', 'queue:monitor')));
    }

    private function writeFailingRuntimeStubs(string $bin): void
    {
        $php = <<<'SH'
#!/usr/bin/env sh
if [ "$1" = "-r" ]; then
    exec __PHP_BINARY__ "$@"
fi

if [ "$1" = "artisan" ] && [ "$2" = "deployment:heartbeat" ]; then
    exit 1
fi

exit 0
SH;
        $php = str_replace('__PHP_BINARY__', escapeshellarg(PHP_BINARY), $php);
        file_put_contents($bin.'/php', $php);
        file_put_contents($bin.'/curl', "#!/usr/bin/env sh\nexit 0\n");
        chmod($bin.'/php', 0755);
        chmod($bin.'/curl', 0755);
    }
}
