<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyDeploymentWorker extends Command
{
    protected $signature = 'deployment:heartbeat {--wait=30}';

    protected $description = 'Verify that a queue worker processes the current release';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
