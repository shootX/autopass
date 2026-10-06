<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PartnersExport;
use App\Exports\WashingsExport;
use App\Http\Controllers\Controller;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\CarWashServicesList;
use App\Models\Review;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class WashingController extends Controller
{
    public function index(Request $request)
    {
        if($request->has('export'))
        {
            return Excel::download(
                new WashingsExport(),
                'washings.xlsx'
            );
        }
        $washings = CarWash::paginate(10);
        return view('admin.washing.index', compact('washings'));
    }

    public function search(Request $request)
    {
        $query = $request->input('q');
        $users = User::where('name', 'like', '%' . $query . '%')
            ->orWhere('surname', 'like', '%' . $query . '%')
            ->limit(10)
            ->get();

        $users = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'text' => $user->name . ' ' . $user->surname . ' (' . $user->email . ')',
            ];
        });
        return response()->json(['items' =>$users]);
    }

    public function search_wash_services(Request $request)
    {
        $query = $request->input('q');
        $carWash = CarWash::find($query);
        $services = $carWash->services()->limit(10)->get();
        $services = $services->map(function ($service) {
            return [
                'id' => $service->id,
                'text' => $service->name,
            ];
        });
        return response()->json(['items' => $services]);
    }

    public function search_user_cars(Request $request)
    {
        $query = $request->input('q');
        $user = User::find($query);
        $cars = $user->cars()->limit(10)->get();
        $cars = $cars->map(function ($car) {
            return [
                'id' => $car->id,
                'text' => $car->model->brand->name . ' ' . $car->model->name . ' (' . $car->plate . ')',
            ];
        });
        return response()->json(['items' => $cars]);
    }

    public function search_wash(Request $request)
    {
        $query = $request->input('q');
        $washes = CarWash::where('name', 'like', '%' . $query . '%')
            ->orWhere('address', 'like', '%' . $query . '%')
            ->limit(10)
            ->get();

        $washes = $washes->map(function ($wash) {
            return [
                'id' => $wash->id,
                'text' => $wash->name . ' (' . $wash->address . ')',
            ];
        });
        return response()->json(['items' => $washes]);
    }

    public function search_clients(Request $request)
    {
        $query = $request->input('q');
        $users = User::where('role', User::ROLE_USER)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('surname', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
                if ($formattedPhone = Phone::normalize($query)) {
                    $q->orWhere('phone', $formattedPhone);
                }
            })
            ->limit(10)
            ->get();

        $users = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'text' => $user->name . ' '. $user->surname . ' (' . $user->email . ')',
            ];
        });
        return response()->json(['items' => $users]);
    }

    public function set_manager_washing(Request $request)
    {
        $manager = $request->input('manager_id');
        $carWash = $request->input('washing_id');

        $washing = CarWash::find($carWash);
        if($washing->manager)
        {
            $washing->manager->role = User::ROLE_USER;
            $washing->manager->update();
        }

        $user = User::find($manager);
        $user->role = User::ROLE_MANAGER;
        $user->update();

        $washing->manager_id = $manager;
        $washing->update();

        return response()->json(['success' => true]);
    }

    public function addPage(Request $request)
    {
        $services = CarWashService::all();
        return view('admin.washing.add', compact('services'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'work_time_start' => 'required',
            'work_time_end' => 'required',
            'location' => 'required',
            'manager_id' => 'required',
            'services' => 'array|nullable',
        ]);

        $addID = CarWash::create([
            'name' => $request->name,
            'address' => $request->address,
            'work_time_start' => $request->work_time_start,
            'work_time_end' => $request->work_time_end,
            'location' => $request->location,
            'manager_id' => $request->manager_id,
        ]);

        if ($request->has('services')) {
            $addID->services()->attach($request->services);
        }

        return redirect()->route('admin.washings')->with('success', __('admin.washing_created'));
    }

    public function remove(Request $request, CarWash $washing)
    {
        $washing->delete();
        return redirect()->route('admin.washings')->with('success', __('admin.washing_deleted'));
    }

    public function editPage(Request $request, CarWash $washing)
    {
        $selectedServices = $washing->services->pluck('id')->toArray();
        $services = CarWashService::all();
        return view('admin.washing.edit', compact('washing', 'services', 'selectedServices'));
    }

    public function edit(Request $request, CarWash $washing)
    {
        $washing->update($request->all());

        CarWashServicesList::where('car_wash_id', $washing->id)->delete();

        if ($request->has('services')) {
            $washing->services()->attach($request->services);
        }

        return redirect()->route('admin.washings')->with('success', __('admin.washing_updated'));
    }

    public function reviews(Request $request, CarWash $washing)
    {
        $reviews = $washing->reviews()->paginate(10);
        return view('admin.washing.reviews.index', compact('washing', 'reviews'));
    }

    public function deleteReview(Request $request, CarWash $washing, Review $review)
    {
        $review->delete();
        return redirect()->route('admin.washings.reviews', ['washing' => $washing->id])->with('success', __('admin.review_deleted'));
    }

    public function addReview(Request $request, CarWash $washing)
    {
        return view('admin.washing.reviews.add', compact('washing'));
    }

    public function storeReview(Request $request, CarWash $washing)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'stars' => 'required|integer|min:1|max:5',
            'text' => 'required|string|max:1000',
        ]);

        Review::create([
            'car_wash_id' => $washing->id,
            'user_id' => $request->user_id,
            'stars' => $request->stars,
            'text' => $request->text,
        ]);

        return redirect()->route('admin.washings.reviews', ['washing' => $washing->id])->with('success', __('admin.review_added'));
    }

    public function toggleReview(Request $request, CarWash $washing, Review $review)
    {
        $review->status = !$review->status;
        $review->update();

        return redirect()->back()->with('success', __('admin.review_toggled'));
    }
}
