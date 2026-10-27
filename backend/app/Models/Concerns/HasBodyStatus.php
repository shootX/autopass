<?php

namespace App\Models\Concerns;

use App\Models\BodyType;
use App\Services\CarBody;

/**
 * A car's body type and whether it is confirmed (CarBody::CONFIRMED, CHOOSE or UNKNOWN). car_type counts only
 * once confirmed: a company car may keep the type of the washes it was given while it waits for a pick.
 */
trait HasBodyStatus
{
    public const BODY_COLUMNS = ['car_type', 'body_status', 'body_options', 'body_incomplete', 'body_source', 'body_checked_at'];

    public function initializeHasBodyStatus(): void
    {
        $this->mergeCasts(['body_options' => 'array', 'body_incomplete' => 'boolean', 'body_checked_at' => 'datetime']);
    }

    public function bodyConfirmed(): bool
    {
        return $this->body_status === CarBody::CONFIRMED && (string) $this->car_type !== '';
    }

    /** The body columns as another car of the same plate takes them over; car_type only once confirmed. */
    public function bodyColumns(): array
    {
        return [
            'car_type' => $this->bodyConfirmed() ? $this->car_type : null,
            'body_status' => $this->body_status ?? CarBody::UNKNOWN,
            'body_options' => $this->body_options,
            'body_incomplete' => (bool) $this->body_incomplete,
            'body_source' => $this->bodyConfirmed() ? $this->body_source : null,
            'body_checked_at' => $this->body_checked_at,
        ];
    }

    /**
     * For the apps and the company API. body_types: what the driver may pick while the status is choose.
     * body_incomplete: geoapi.ge named no type for some variants, so the right one may be missing.
     */
    public function bodyPayload(): array
    {
        $status = $this->body_status ?? CarBody::UNKNOWN;

        return [
            'body_status' => $status,
            'body_types' => $status === CarBody::CHOOSE
                ? array_map(fn (string $key) => ['key' => $key, 'name' => BodyType::labelFor($key)], $this->body_options ?? [])
                : [],
            'body_incomplete' => (bool) $this->body_incomplete,
        ];
    }
}
