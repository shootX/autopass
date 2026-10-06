<?php

namespace App\Exports;

use App\Models\VoucherCategory;
use Maatwebsite\Excel\Concerns\FromCollection;

class CreatedVouchersCategoryExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return VoucherCategory::all();
    }
}
