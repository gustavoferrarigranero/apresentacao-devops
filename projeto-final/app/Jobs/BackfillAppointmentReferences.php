<?php

namespace App\Jobs;

use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BackfillAppointmentReferences implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public array $appointmentIds) {}

    public function handle(): void
    {
        Appointment::query()
            ->whereKey($this->appointmentIds)
            ->whereNull('external_reference')
            ->eachById(function (Appointment $appointment): void {
                $appointment->update([
                    'external_reference' => 'APT-'.str_pad((string) $appointment->id, 8, '0', STR_PAD_LEFT),
                ]);
            });
    }
}
