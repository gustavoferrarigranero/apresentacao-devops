<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackfillAppointmentReferences extends Command
{
    protected $signature = 'appointments:backfill-references {--chunk=500}';

    protected $description = 'Dispatch compatible appointment reference backfills';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
