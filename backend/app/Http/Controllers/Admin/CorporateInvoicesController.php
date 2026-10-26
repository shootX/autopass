<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Services\CorporateInvoices;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin side of corporate invoices: the list across companies, issuing one, paid and cancel. */
class CorporateInvoicesController extends Controller
{
    private const STATUSES = [CorporateInvoice::STATUS_ISSUED, CorporateInvoice::STATUS_PAID, CorporateInvoice::STATUS_CANCELLED];

    /** Unpaid invoices first by default: they are the ones waiting on the admin. */
    public function index(Request $request)
    {
        $status = $request->query('status', CorporateInvoice::STATUS_ISSUED);
        $status = in_array($status, self::STATUSES, true) ? $status : null;
        $invoices = CorporateInvoice::query()
            ->with('client:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.corporate.invoices-index', compact('invoices', 'status'));
    }

    public function store(Request $request, CorporateClient $corporate)
    {
        $data = $request->validate([
            'corporate_package_id' => ['required', 'integer', Rule::exists('corporate_packages', 'id')->where('corporate_client_id', $corporate->id)],
        ]);
        $result = CorporateInvoices::issue($corporate, CorporatePackage::query()->findOrFail($data['corporate_package_id']), 'admin');
        if (is_string($result)) {
            return $this->back($corporate->id, null, __('admin.inv_error_'.$result, ['count' => config('invoices.open_limit')]));
        }

        return $this->back($corporate->id, __('admin.inv_ordered', ['number' => $result->number]));
    }

    public function show(CorporateInvoice $invoice)
    {
        return view('invoices.document', ['invoice' => $invoice]);
    }

    public function paid(CorporateInvoice $invoice)
    {
        if (CorporateInvoices::markPaid($invoice, (int) auth()->id()) !== 'ok') {
            return $this->back($invoice->corporate_client_id, null, __('admin.inv_error_not_open'));
        }

        return $this->back($invoice->corporate_client_id, __('admin.inv_paid', ['count' => $invoice->washes]));
    }

    public function cancel(CorporateInvoice $invoice)
    {
        if (CorporateInvoices::cancel($invoice) !== 'ok') {
            return $this->back($invoice->corporate_client_id, null, __('admin.inv_error_not_open'));
        }

        return $this->back($invoice->corporate_client_id, __('admin.inv_cancelled'));
    }

    /** Back to the company page's invoices, or to the list when the company is gone. */
    private function back(?int $corporateId, ?string $message, ?string $error = null)
    {
        $to = $corporateId && CorporateClient::query()->whereKey($corporateId)->exists()
            ? route('admin.corporate.show', $corporateId).'#invoices'
            : route('admin.corporate.invoices');
        $redirect = redirect()->to($to);

        return $error !== null ? $redirect->withErrors(['invoice' => $error]) : $redirect->with('message', $message);
    }
}
