<?php

namespace App\Exports;

use App\Models\CarWash;
use Maatwebsite\Excel\Concerns\FromCollection;

class WashingsExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return CarWash::all();
    }
}
