<?php

namespace App\Console\Commands;

use App\Jobs\RecordDeploymentProbe;
use App\Models\DeploymentProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class VerifyDeploymentWorker extends Command
{
    protected $signature = 'deployment:heartbeat {--wait=30}';

    protected $description = 'Verify that a queue worker processes the current release';

    public function handle(): int
    {
        $token = (string) Str::uuid();
        $release = (string) config('app.release');
        RecordDeploymentProbe::dispatch($token, $release);
        $wait = max(0, (int) $this->option('wait'));

        if ($wait === 0) {
            $this->components->info("Deployment probe {$token} dispatched.");

            return self::SUCCESS;
        }

        $deadline = microtime(true) + $wait;

        do {
            if (DeploymentProbe::query()->where('token', $token)->whereNotNull('processed_at')->exists()) {
                $this->components->info("Worker processed release {$release}.");

                return self::SUCCESS;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        $this->components->error('Worker did not process the deployment probe in time.');

        return self::FAILURE;
    }
}
