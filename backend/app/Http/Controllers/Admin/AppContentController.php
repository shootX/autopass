<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarWashService;
use App\Models\Contact;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** What the app reads as it is: FAQ (/api/faq), contacts (/api/contacts) and the services list (/api/services-list). */
class AppContentController extends Controller
{
    public function index()
    {
        return view('admin.content.index', [
            'faqs' => Faq::query()->orderBy('id')->get(),
            'contact' => Contact::query()->first(),
            'services' => CarWashService::query()->orderBy('name')
                ->select('car_wash_services.*')
                ->selectSub(DB::table('car_wash_services_lists')->selectRaw('count(*)')
                    ->whereColumn('car_wash_service_id', 'car_wash_services.id'), 'branches_count')
                ->get(),
        ]);
    }

    public function storeFaq(Request $request)
    {
        Faq::query()->forceCreate($this->faqData($request));
        Cache::forget('faq');

        return $this->back('content_faq_saved');
    }

    public function saveFaq(Request $request, Faq $faq)
    {
        $faq->forceFill($this->faqData($request))->save();
        Cache::forget('faq');

        return $this->back('content_faq_saved');
    }

    public function deleteFaq(Faq $faq)
    {
        $faq->delete();
        Cache::forget('faq');

        return $this->back('content_faq_deleted');
    }

    public function saveContacts(Request $request)
    {
        $data = $request->validate([
            'address' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:64',
            'website' => 'required|string|max:255',
            'working_hours' => 'required|string|max:255',
        ]);
        $contact = Contact::query()->first() ?? new Contact;
        $contact->forceFill($data)->save();
        Cache::forget('contacts_first');

        return $this->back('content_contacts_saved');
    }

    public function storeService(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:64|unique:car_wash_services,name']);
        CarWashService::query()->forceCreate($data);

        return $this->back('content_service_saved');
    }

    public function saveService(Request $request, CarWashService $service)
    {
        $data = $request->validate(['name' => 'required|string|max:64|unique:car_wash_services,name,'.$service->id]);
        $service->forceFill($data)->save();

        return $this->back('content_service_saved');
    }

    /** A service still offered by a branch or named on a booking stays: those rows point at it. */
    public function deleteService(CarWashService $service)
    {
        $used = DB::table('car_wash_services_lists')->where('car_wash_service_id', $service->id)->exists()
            || DB::table('appointments_services')->where('car_wash_service_id', $service->id)->exists();
        if ($used) {
            return redirect()->route('admin.content')->with('error', __('admin.content_service_in_use'));
        }
        $service->delete();

        return $this->back('content_service_deleted');
    }

    private function faqData(Request $request): array
    {
        return $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
        ]);
    }

    private function back(string $message)
    {
        return redirect()->route('admin.content')->with('message', __('admin.'.$message));
    }
}
