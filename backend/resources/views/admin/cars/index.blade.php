@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>{{ __('admin.cars_menu') }} <span class="text-muted">({{ $cars->total() }})</span></div>
                <form method="GET" class="d-flex flex-wrap gap-2">
                    <input style="max-width: 260px;" value="{{ request('search') }}" class="form-control" type="text" name="search" placeholder="{{ __('admin.cars_search') }}">
                    <select name="kind" class="form-select" style="max-width: 190px;">
                        <option value="">{{ __('admin.all') }}</option>
                        <option value="personal" @selected(request('kind') === 'personal')>{{ __('admin.cars_kind_personal') }}</option>
                        <option value="fleet" @selected(request('kind') === 'fleet')>{{ __('admin.cars_kind_fleet') }}</option>
                    </select>
                    <select name="company" class="form-select" style="max-width: 220px;">
                        <option value="">{{ __('admin.cars_any_company') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) request('company') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-secondary" type="submit">{{ __('admin.search') }}</button>
                </form>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <th scope="col">{{ __('admin.plate') }}</th>
                        <th scope="col">{{ __('admin.brand') }} / {{ __('admin.model') }}</th>
                        <th scope="col">{{ __('admin.p_year') }}</th>
                        <th scope="col">{{ __('admin.p_vin') }}</th>
                        <th scope="col">{{ __('admin.p_color') }}</th>
                        <th scope="col">{{ __('admin.cars_owner') }}</th>
                        <th scope="col">{{ __('admin.company') }}</th>
                        <th scope="col">{{ __('admin.cars_added') }}</th>
                    </thead>
                    <tbody>
                    @forelse($cars as $car)
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $car->plate }}</td>
                            <td>{{ trim(($car->brand ?? '').' '.($car->model ?? '')) ?: '—' }}</td>
                            <td>{{ $car->year ?: '—' }}</td>
                            <td class="text-nowrap"><code>{{ $car->vin ?: '—' }}</code></td>
                            <td>{{ $car->color ? \Illuminate\Support\Arr::get(trans('admin.car_colors'), $car->color, $car->color) : '—' }}</td>
                            <td>
                                @if($car->owner_id)
                                    <a href="{{ route('admin.clients.show', $car->owner_id) }}">{{ trim($car->owner_name.' '.$car->owner_surname) ?: \App\Support\Phone::format($car->owner_phone) }}</a>
                                    @if(trim($car->owner_name.' '.$car->owner_surname))
                                        <div class="small text-muted">{{ \App\Support\Phone::format($car->owner_phone) }}</div>
                                    @endif
                                @elseif($car->owner_phone)
                                    {{ \App\Support\Phone::format($car->owner_phone) }}
                                    <div class="small text-muted">{{ __('admin.cars_driver_invited') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($car->company_id)
                                    <a href="{{ route('admin.corporate.show', $car->company_id) }}">{{ $car->company_name }}</a>
                                    @if($car->fleet_status)
                                        <div class="small text-muted">{{ __('admin.cars_status_'.$car->fleet_status) }}</div>
                                    @endif
                                @else
                                    <span class="text-muted">{{ __('admin.cars_kind_personal') }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $car->created_at ? \Illuminate\Support\Carbon::parse($car->created_at)->format('d.m.Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $cars->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
