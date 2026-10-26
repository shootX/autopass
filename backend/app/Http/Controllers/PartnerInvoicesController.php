<?php

namespace App\Http\Controllers;

use App\Models\CorporateClient;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Services\CorporateInvoices;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Company panel: order a wash package, see and print the invoices, cancel an unpaid one. */
class PartnerInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $corporate = $this->corporate($request);

        return view('partner.invoices', [
            'corporate' => $corporate,
            'invoices' => $corporate->invoices()->orderByDesc('id')->paginate(20),
            'orderable' => $corporate->packages()->orderBy('washes')->get()
                ->filter(fn (CorporatePackage $package) => CorporateInvoices::orderable($corporate, $package))->values(),
            'manage' => false,
        ]);
    }

    public function store(Request $request)
    {
        $corporate = $this->corporate($request);
        $data = $request->validate([
            'corporate_package_id' => ['required', 'integer', Rule::exists('corporate_packages', 'id')->where('corporate_client_id', $corporate->id)],
        ]);
        $result = CorporateInvoices::issue($corporate, CorporatePackage::query()->findOrFail($data['corporate_package_id']), 'company');
        if (is_string($result)) {
            return redirect()->route('partner.invoices')->withErrors(['invoice' => __('admin.inv_error_'.$result, ['count' => config('invoices.open_limit')])]);
        }

        return redirect()->route('partner.invoices')->with('message', __('admin.inv_ordered', ['number' => $result->number]));
    }

    public function show(Request $request, CorporateInvoice $invoice)
    {
        $this->own($request, $invoice);

        return view('invoices.document', ['invoice' => $invoice]);
    }

    public function cancel(Request $request, CorporateInvoice $invoice)
    {
        $this->own($request, $invoice);
        if (CorporateInvoices::cancel($invoice) !== 'ok') {
            return redirect()->route('partner.invoices')->withErrors(['invoice' => __('admin.inv_error_not_open')]);
        }

        return redirect()->route('partner.invoices')->with('message', __('admin.inv_cancelled'));
    }

    private function corporate(Request $request): CorporateClient
    {
        return $request->attributes->get('corporate');
    }

    private function own(Request $request, CorporateInvoice $invoice): void
    {
        abort_unless((int) $invoice->corporate_client_id === (int) $this->corporate($request)->id, 404);
    }
}
