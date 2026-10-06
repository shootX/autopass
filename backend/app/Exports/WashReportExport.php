<?php

namespace App\Exports;

use App\Models\Appointment;
use App\Services\WashReportQuery;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WashReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly array $filters)
    {
    }

    public function query()
    {
        return WashReportQuery::make($this->filters);
    }

    public function headings(): array
    {
        return [
            'ID',
            __('admin.date'),
            __('admin.time'),
            __('admin.status'),
            __('admin.client'),
            __('admin.phone'),
            __('admin.email'),
            __('admin.plate'),
            __('admin.brand'),
            __('admin.model_name'),
            __('admin.body_type'),
            __('admin.car_wash'),
            __('admin.address'),
            __('admin.manager'),
            __('admin.services'),
            'QR',
            __('admin.package'),
            __('admin.wash_count'),
            __('admin.created_at'),
        ];
    }

    /**
     * @param  Appointment  $appointment
     */
    public function map($appointment): array
    {
        $car = $appointment->car;
        $model = $car?->model;
        $package = $car?->package?->package;
        $wash = $appointment->washing;
        $manager = $wash?->manager;

        return [
            $appointment->id,
            Carbon::parse($appointment->date)->format('d.m.Y'),
            Carbon::parse($appointment->time)->format('H:i'),
            WashReportQuery::statusLabel($appointment->approved),
            $appointment->user?->displayName(),
            \App\Support\Phone::format($appointment->user?->phone),
            $appointment->user?->email,
            $car?->plate,
            $model?->brand?->name,
            $model?->name,
            $model?->type,
            $wash?->name,
            $wash?->address,
            $manager ? trim($manager->name.' '.$manager->surname) : null,
            $appointment->servicesList,
            $appointment->qr_code,
            $package?->car_type,
            $package?->count_washes,
            optional($appointment->created_at)->format('d.m.Y H:i'),
        ];
    }
}
