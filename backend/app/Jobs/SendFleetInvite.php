<?php

namespace App\Jobs;

use App\Models\FleetCar;
use App\Services\FleetInvites;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** A company changed the driver inside the resend window: the new driver's invite, once the window closes. */
class SendFleetInvite implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $requestedAt;

    public function __construct(public int $carId, public string $phone)
    {
        $this->requestedAt = now()->getTimestamp();
    }

    public function handle(): void
    {
        $car = FleetCar::query()->with('client')->find($this->carId);
        // Only for the driver it was meant for, while the car still waits for them.
        if (! $car || $car->driver_phone !== $this->phone || $car->status !== FleetCar::STATUS_INVITED) {
            return;
        }
        // An invite went out after this one was asked for (a resend, or an earlier queued one): nothing to add.
        if ($car->invited_at && $car->invited_at->getTimestamp() > $this->requestedAt) {
            return;
        }
        if (FleetInvites::invite($car) === 'too_soon' && $this->job) {
            // Someone resent in between: try once that window closes.
            $this->release(max(1, now()->diffInSeconds(FleetInvites::resendAt($car), false)));
        }
    }
}
