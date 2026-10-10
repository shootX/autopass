<?php

namespace App\Exports;

use App\Models\Appointment;
use App\Services\WashReportQuery;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PartnerFleetReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly array $filters, private readonly array $plates)
    {
    }

    public function query()
    {
        return WashReportQuery::forPlates($this->filters, $this->plates);
    }

    public function headings(): array
    {
        return [
            __('admin.date'),
            __('admin.time'),
            __('admin.plate'),
            __('admin.brand'),
            __('admin.model_name'),
            __('admin.car_wash'),
            __('admin.services'),
            __('admin.status'),
        ];
    }

    /**
     * @param  Appointment  $appointment
     */
    public function map($appointment): array
    {
        $car = $appointment->car;

        return [
            Carbon::parse($appointment->date)->format('d.m.Y'),
            Carbon::parse($appointment->time)->format('H:i'),
            $car?->plate,
            $car?->model?->brand?->name,
            $car?->model?->name,
            $appointment->washing?->name,
            $appointment->servicesList,
            WashReportQuery::statusLabel($appointment->approved),
        ];
    }
}
