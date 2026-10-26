<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateClient;
use App\Models\CorporatePackage;
use App\Models\CorporateWashMove;
use App\Services\CorporateWashes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin side of a company's washes: its own packages, credits after a paid invoice, corrections. */
class CorporateWashesController extends Controller
{
    public function storePackage(Request $request, CorporateClient $corporate)
    {
        $corporate->packages()->create($this->packageFields($request) + ['is_active' => true]);

        return $this->back($corporate, __('admin.cw_package_saved'));
    }

    public function savePackage(Request $request, CorporateClient $corporate, CorporatePackage $package)
    {
        $this->own($corporate, $package);
        $package->update($this->packageFields($request) + ['is_active' => $request->boolean('is_active')]);

        return $this->back($corporate, __('admin.cw_package_saved'));
    }

    public function deletePackage(CorporateClient $corporate, CorporatePackage $package)
    {
        $this->own($corporate, $package);
        // Ledger rows keep the washes; they only lose the link to the package.
        $package->delete();

        return $this->back($corporate, __('admin.cw_package_deleted'));
    }

    /** Credits a package's washes (the invoice is paid) or a manual amount. */
    public function credit(Request $request, CorporateClient $corporate)
    {
        $data = $request->validate([
            'corporate_package_id' => ['nullable', 'integer', Rule::exists('corporate_packages', 'id')->where('corporate_client_id', $corporate->id)],
            'amount' => ['required_without:corporate_package_id', 'nullable', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $package = empty($data['corporate_package_id']) ? null : CorporatePackage::query()->find($data['corporate_package_id']);
        $amount = $package ? (int) $package->washes : (int) $data['amount'];

        $result = CorporateWashes::credit($corporate, CorporateWashMove::TYPE_CREDIT, $amount, $package, $data['note'] ?? null, (int) auth()->id());

        return $this->result($corporate, $result, __('admin.cw_credited', ['count' => $amount]));
    }

    /** A signed fix of the pool, always with a reason. */
    public function correct(Request $request, CorporateClient $corporate)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $result = CorporateWashes::credit($corporate, CorporateWashMove::TYPE_CORRECTION, (int) $data['amount'], null, $data['note'], (int) auth()->id());

        return $this->result($corporate, $result, __('admin.cw_corrected'));
    }

    private function packageFields(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'washes' => ['required', 'integer', 'min:1', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
    }

    private function result(CorporateClient $corporate, string $result, string $message)
    {
        if ($result !== 'ok') {
            return redirect()->to(route('admin.corporate.show', $corporate).'#washes')
                ->withErrors(['washes' => __('admin.cw_error_'.$result)]);
        }

        return $this->back($corporate, $message);
    }

    private function back(CorporateClient $corporate, string $message)
    {
        return redirect()->to(route('admin.corporate.show', $corporate).'#washes')->with('message', $message);
    }

    private function own(CorporateClient $corporate, CorporatePackage $package): void
    {
        abort_unless((int) $package->corporate_client_id === (int) $corporate->id, 404);
    }
}
