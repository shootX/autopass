<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }

    public function collection()
    {
        return $this->users;
    }

    public function headings(): array
    {
        return [
            __('admin.user'),
            __('admin.datetime'),
        ];
    }

    public function map($item): array
    {
        return [
            $item->user->name,
            $item->created_at->format('d.m.Y H:i'),
        ];
    }
}
