<?php

namespace App\Console\Commands;

use App\Jobs\BackfillAppointmentReferences as BackfillJob;
use App\Models\Appointment;
use Illuminate\Console\Command;

class BackfillAppointmentReferences extends Command
{
    protected $signature = 'appointments:backfill-references {--chunk=500}';

    protected $description = 'Dispatch compatible appointment reference backfills';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $jobs = 0;

        Appointment::query()
            ->whereNull('external_reference')
            ->select('id')
            ->chunkById($chunkSize, function ($appointments) use (&$jobs): void {
                BackfillJob::dispatch($appointments->pluck('id')->all());
                $jobs++;
            });

        $this->components->info("{$jobs} backfill jobs dispatched.");

        return self::SUCCESS;
    }
}
