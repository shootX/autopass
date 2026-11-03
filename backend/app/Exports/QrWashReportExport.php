<?php

namespace App\Exports;

use App\Models\BodyType;
use App\Services\QrWashReport;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class QrWashReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly array $filters) {}

    public function query()
    {
        return QrWashReport::make($this->filters);
    }

    public function headings(): array
    {
        return [
            __('admin.qr_wash_time'),
            __('admin.qr_wash_kind'),
            __('admin.plate'),
            __('admin.client'),
            __('admin.body_type'),
            __('admin.car_wash'),
            __('admin.address'),
            __('admin.manager'),
            __('admin.qr_wash_booking'),
            __('admin.qr_wash_full_price'),
            __('admin.qr_wash_paid_on_site'),
        ];
    }

    /** @param  object  $row */
    public function map($row): array
    {
        return [
            Carbon::parse($row->created_at)->format('d.m.Y H:i'),
            __('admin.qr_wash_kind_'.$row->kind),
            $row->plate,
            QrWashReport::client($row),
            BodyType::labelFor($row->car_type),
            $row->wash_name,
            $row->wash_address,
            QrWashReport::manager($row),
            $row->appointment_id ? '#'.$row->appointment_id : __('admin.qr_wash_walk_in'),
            $row->full_price,
            $row->to_pay,
        ];
    }
}
