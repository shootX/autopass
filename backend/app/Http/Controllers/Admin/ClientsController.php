<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ClientsExport;
use App\Http\Controllers\Controller;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Services\CarBodyStatus;
use App\Support\AdminAudit;
use App\Support\Phone;
use App\Support\SessionRevoker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ClientsController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $clients = User::query()->where('role', User::ROLE_USER);

        if ($request->search) {
            $search = $request->search;
            $clients->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('surname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
                if ($formattedPhone = Phone::normalize($search)) {
                    $query->orWhere('phone', $formattedPhone);
                }
            });
        }

        if ($request->has('export')) {
            return Excel::download(
                new ClientsExport,
                'clients.xlsx'
            );
        }

        $sort = $request->query('sort', 'id');
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, ['id', 'created_at'], true)) {
            $sort = 'id';
        }

        $clients = $clients->orderBy($sort, $dir)->paginate(10)->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function ban(Request $request, User $client)
    {
        $client->forceFill(['ban' => ! $client->ban])->save();
        // Existing JWTs and web sessions stop working at once (middleware also checks the ban flag).
        SessionRevoker::user($client);
        AdminAudit::record('user', (int) $client->id, $client->ban ? 'ban' : 'unban');

        return redirect()->back()->with('message', __('admin.client_ban_status', [
            'status' => $client->ban ? __('admin.banned') : __('admin.unbanned'),
        ]));
    }

    public function edit(Request $request, User $client)
    {
        return view('admin.clients.edit', compact('client'));
    }

    public function edit_save(Request $request, User $client)
    {
        abort_unless((int) $request->user()?->role === User::ROLE_ADMIN, 403);
        Phone::prepare($request);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$client->id,
            'phone' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail) use ($client) {
                    if (Phone::normalize((string) $value) !== null) {
                        return;
                    }
                    if (Phone::digits((string) $value) !== '' && Phone::digits((string) $value) === Phone::digits($client->phone)) {
                        return;
                    }
                    $fail(__('validation.regex', ['attribute' => $attribute]));
                },
                Rule::unique('users', 'phone')->ignore($client->id),
            ],
            'sex' => 'required|in:male,female',
            'date_of_birth' => 'date',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $oldEmail = (string) $client->email;
        $oldPhone = (string) $client->phone;

        $client->name = $request->name;
        $client->surname = $request->surname;
        $client->email = $request->email;
        $client->phone = $request->phone;
        $client->sex = $request->sex;
        $client->date_of_birth = $request->date_of_birth;

        $msg = __('admin.client_updated');

        if ($request->filled('password')) {
            $client->password = Hash::make($request->password);
            $msg .= ' '.__('admin.password_also_updated');
        }

        $client->update();

        $changed = [];
        if (mb_strtolower($oldEmail) !== mb_strtolower((string) $client->email)) {
            $changed['email'] = [$oldEmail, (string) $client->email];
        }
        if (Phone::digits($oldPhone) !== Phone::digits((string) $client->phone)) {
            $changed['phone'] = [$oldPhone, (string) $client->phone];
        }
        foreach ($changed as $field => [$old, $new]) {
            AdminAudit::record('user', (int) $client->id, 'update', $field, $old, $new);
        }
        if ($changed !== [] || $request->filled('password')) {
            // Login identifiers changed: whoever holds the old sessions must sign in again.
            SessionRevoker::user($client);
        }

        return redirect()->route('admin.clients')->with('message', $msg);
    }

    public function delete(Request $request, User $client)
    {
        if ($client->id == auth()->id()) {
            return redirect()->back()->with('error', __('admin.cannot_delete_self'));
        }
        $client->delete();

        return redirect()->route('admin.clients')->with('message', __('admin.client_deleted'));
    }

    public function packages(Request $request, User $client)
    {
        $userPackages = $client->packages()->paginate(10);

        return view('admin.clients.packages.packages', compact('client', 'userPackages'));
    }

    public function renewPackage(Request $request, User $client)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:user_packages,id',
            'date' => 'required|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $package = $client->packages()->where('id', (int) $request->package_id)
            ->first();

        if (! $package) {
            return redirect()->back()->with('error', __('admin.package_not_found'));
        }

        $package->end_date = $request->date;
        $package->update();

        return redirect()->route('admin.clients.packages', ['client' => $client->id])
            ->with('message', __('admin.package_renewed'));
    }

    public function addUserPackage(Request $request, User $client)
    {
        return view('admin.clients.packages.add_package', compact('client'));
    }

    public function getPackagesForCar(Request $request, UserCar $car)
    {
        try {
            if ($car && $car->carType() !== null) {
                $type = $car->carType();

                // Every package of the body type: washes ones by term, discount ones for their 30 days.
                $html = '';
                foreach (Package::where('car_type', $type)->with('prices')->get() as $package) {
                    foreach ($package->prices as $price) {
                        $label = $package->isDiscount()
                            ? __('admin.discount_package_option', ['percent' => $package->percent, 'price' => $price->price,
                                'washes' => $package->count_washes > 0 ? $package->count_washes : __('admin.discount_unlimited'),
                                'days' => Package::DISCOUNT_DAYS])
                            : __('admin.package_price_option', ['month' => $price->month, 'price' => $price->price,
                                'washes' => $package->count_washes]);
                        $html .= '<option value="'.$price->id.'">'.e($label).'</option>';
                    }
                }

                return $html;
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function storeUserPackage(Request $request, User $client)
    {
        $validator = Validator::make($request->all(), [
            'car_id' => 'required|exists:user_cars,id',
            'price_id' => 'required|exists:package_prices,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $price = PackagePrice::with('package')->find($request->price_id);
        $car = $client->cars()->find($request->car_id);
        if (! $car) {
            return redirect()->back()->withInput()->withErrors(['car_id' => __('cars.car_not_found')]);
        }
        // A package goes only to a car of a confirmed body type, and only one of that type.
        if ($car->carType() === null || $price->package?->car_type !== $car->carType()) {
            return redirect()->back()->withInput()->withErrors(['car_id' => __('cars.body_unconfirmed')]);
        }

        UserPackage::create([
            'user_id' => $client->id,
            'package_id' => $price->package_id,
            'price_id' => $price->id,
            'user_car_id' => $request->car_id,
            'start_date' => now(),
            'qr_code' => Str::random(16),
        ] + $price->package->termsFor($price));

        return redirect()->route('admin.clients.packages', ['client' => $client->id])
            ->with('message', __('admin.user_package_added'));
    }

    public function packageDelete(Request $request, User $client, UserPackage $pack)
    {
        abort_unless((int) $pack->user_id === (int) $client->id, 404);
        $pack->delete();

        return redirect()->route('admin.clients.packages', ['client' => $client->id])
            ->with('message', __('admin.user_package_deleted'));
    }

    public function cars(Request $request, User $client)
    {
        $userCars = $client->cars()->paginate(10);

        return view('admin.clients.cars.index', compact('client', 'userCars'));
    }

    public function carDelete(Request $request, User $client, UserCar $car)
    {
        $car->delete();

        return redirect()->route('admin.clients.cars', $client->id)->with('message', __('admin.car_deleted'));
    }

    public function addUserCar(Request $request, User $client)
    {
        return view('admin.clients.cars.add', compact('client'));
    }

    public function search_car_brands(Request $request)
    {
        $query = $request->input('q');

        $brands = CarBrand::where('name', 'like', '%'.$query.'%')->get();

        $brands = $brands->map(function ($brand) {
            return [
                'id' => $brand->id,
                'text' => $brand->name,
            ];
        });

        return response()->json(['items' => $brands]);
    }

    public function search_car_models(Request $request)
    {
        $brand_id = $request->brand_id;

        $models = CarModel::where('brand_id', $brand_id)->get();

        $models = $models->map(function ($model) {
            return [
                'id' => $model->id,
                'text' => $model->name,
            ];
        });

        return response()->json(['items' => $models]);
    }

    public function storeUserCar(Request $request, User $client)
    {
        $validate = Validator::make($request->all(), [
            'brand_id' => 'required|exists:car_brands,id',
            'model_id' => [
                'required',
                Rule::exists('car_models', 'id')->where(fn ($query) => $query->where('brand_id', $request->input('brand_id'))),
            ],
            'plate' => 'required|string|max:20|unique:user_cars,plate',
        ]);

        if ($validate->fails()) {
            return redirect()->back()
                ->withErrors($validate)
                ->withInput();
        }

        $car = UserCar::create([
            'user_id' => $client->id,
            'model_id' => $request->model_id,
            'plate' => $request->plate,
        ]);
        // No year here: geoapi.ge is asked about the model in every year; the list shows what is still to confirm.
        app(CarBodyStatus::class)->recheck($car, 'admin');

        return redirect()->route('admin.clients.cars', ['client' => $client->id])
            ->with('message', __('admin.car_added'));
    }
}
