<?php

namespace App\Jobs;

use App\Models\DeploymentProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordDeploymentProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(public string $token, public string $release) {}

    public function handle(): void
    {
        DeploymentProbe::query()->updateOrCreate(
            ['token' => $this->token],
            ['release' => $this->release, 'processed_at' => now()],
        );
    }
}
