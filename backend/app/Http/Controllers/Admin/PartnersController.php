<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ManagersExport;
use App\Exports\PartnersExport;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class PartnersController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new PartnersExport(),
                'partners.xlsx'
            );
        }
        return redirect()->route('admin.corporate');
    }

    public function delete(Request $request, Partner $client)
    {
        $client->delete();
        return redirect()->route('admin.partners')->with('message', __('admin.partner_deleted'));
    }

    public function addPage(Request $request)
    {
        return redirect()->route('admin.corporate.add');
    }

    public function editPage(Request $request, Partner $client)
    {
        return view('admin.partners.edit', compact('client'));
    }

    public function editSave(Request $request, Partner $client)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'description' => 'required|min:5',
            'password' => 'sometimes|nullable|string|min:3',
            Rule::unique('partners')->ignore($client->id, 'id')
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $data = $validate->validated();

        if($request->has('password') && $request->password != null)
        {
            $data["password"] = Hash::make($data["password"]);
        }

        $client->update($data);

        return redirect()->route('admin.partners')->with('message', __('admin.partner_updated'));

    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
            'description' => 'required|min:5',
            'login' => 'required|string|min:3|unique:partners',
            'password' => 'required|string|min:3',
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $data = $validate->validated();

        $data["password"] = Hash::make($data["password"]);

        Partner::create($data);

        return redirect()->route('admin.partners')->with('message', __('admin.partner_created'));

    }
}
