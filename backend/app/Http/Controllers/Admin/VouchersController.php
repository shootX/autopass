<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CreatedTicketsExport;
use App\Exports\CreatedVouchersCategoryExport;
use App\Exports\CreatedVouchersExport;
use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class VouchersController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new CreatedVouchersExport(),
                'vouchers.xlsx'
            );
        }
        $vouchers = Voucher::paginate(10);
        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function addPage(Request $request)
    {
        $categories = VoucherCategory::all();
        return view('admin.vouchers.add', compact('categories'));
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'category_id' => 'required|exists:voucher_categories,id',
            'name' => 'required|string|min:3',
            'voucher_type' => 'required|in:1,2',
            'description' => 'required|min:5',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'required|numeric',
            'percent' => 'sometimes|numeric|min:0|max:100|nullable',
            'conditions' => 'sometimes',
            'footer_text' => 'required',
            'footer_geo' => 'required',
            'count' => 'nullable|numeric',
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = '/storage/'.$request->file('photo')->store('vouchers', 'public');
        }

        $data = $validate->validated();

        if($data["count"] == null)
        {
            $data["count"] = 0;
        }

        $data['photo'] = $photoPath;

        unset($data['voucher_type']);

        Voucher::create($data);

        return redirect()->route('admin.vouchers')->with('message', __('admin.voucher_created'));

    }

    public function delete(Request $request, Voucher $voucher)
    {
        $voucher->update(
            [
            'deleted' => true
            ]
        );
        return redirect()->back()->with('message', __('admin.voucher_disabled'));
    }

    public function categoriesIndex(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new CreatedVouchersCategoryExport(),
                'vouchers_categories.xlsx'
            );
        }
        $categories = VoucherCategory::paginate(10);
        return view('admin.vouchers.categories.index', compact('categories'));
    }

    public function categorieAdd(Request $request)
    {
        return view('admin.vouchers.categories.add');
    }

    public function categorieStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
        ]);

        if($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        VoucherCategory::create($validator->validated());

        return redirect()->route('admin.vouchers.categories')->with('message', __('admin.category_created'));

    }

    public function categorieDelete(Request $request, VoucherCategory $categorie)
    {
        $categorie->delete();
        return redirect()->back()->with('message', __('admin.category_deleted'));
    }

    public function editPage(Request $request, VoucherCategory $categorie)
    {
        return view('admin.vouchers.categories.edit', compact('categorie'));
    }

    public function save(Request $request, VoucherCategory $categorie)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3',
        ]);

        if($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $categorie->update($validator->validated());

        return redirect()->route('admin.vouchers.categories')->with('message', __('admin.category_updated'));

    }

    public function editPageVoucher(Request $request, Voucher $voucher)
    {
        $categories = VoucherCategory::all();
        return view('admin.vouchers.edit', compact('voucher', 'categories'));
    }

    public function saveVoucher(Request $request, Voucher $voucher)
    {
        $validate = Validator::make($request->all(), [
            'category_id' => 'required|exists:voucher_categories,id',
            'name' => 'required|string|min:3',
            'voucher_type' => 'required|in:1,2',
            'description' => 'required|min:5',
            'photo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'price' => 'required|numeric',
            'percent' => 'sometimes|numeric|min:0|max:100|nullable',
            'conditions' => 'sometimes',
            'footer_text' => 'required',
            'footer_geo' => 'required',
            'count' => 'nullable|numeric',
        ]);

        if($validate->fails()) {
            return redirect()->back()->withErrors($validate)->withInput();
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = '/storage/'.$request->file('photo')->store('vouchers', 'public');
        }

        $data = $validate->validated();

        unset($data['voucher_type']);

        if($photoPath)
        {
            $data['photo'] = $photoPath;
        } else
        {
            unset($data['photo']);
        }

        $voucher->update($data);

        return redirect()->route('admin.vouchers')->with('message', __('admin.voucher_updated'));

    }
}
