<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ClientsExport;
use App\Exports\ManagersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ManagerController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new ManagersExport(),
                'managers.xlsx'
            );
        }
        $managers = User::where('role', User::ROLE_MANAGER)->paginate(10);
        return view('admin.managers.index', compact('managers'));
    }



}
