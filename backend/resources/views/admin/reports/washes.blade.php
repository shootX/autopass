@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4">
            <div class="card-header">{{ __('admin.wash_report') }}</div>
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label" for="id">ID</label>
                            <input class="form-control" id="id" name="id" value="{{ request('id') }}" inputmode="numeric">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="date_from">{{ __('admin.date_from') }}</label>
                            <input class="form-control" type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="date_to">{{ __('admin.date_to') }}</label>
                            <input class="form-control" type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="time_from">{{ __('admin.time_from') }}</label>
                            <input class="form-control" type="time" id="time_from" name="time_from" value="{{ request('time_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="time_to">{{ __('admin.time_to') }}</label>
                            <input class="form-control" type="time" id="time_to" name="time_to" value="{{ request('time_to') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="approved">{{ __('admin.status') }}</label>
                            <select class="form-select" id="approved" name="approved">
                                <option value="">{{ __('admin.all') }}</option>
                                <option value="0" @selected(request('approved') === '0')>{{ __('admin.pending') }}</option>
                                <option value="1" @selected(request('approved') === '1')>{{ __('admin.confirmed') }}</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label" for="created_from">{{ __('admin.created_from') }}</label>
                            <input class="form-control" type="date" id="created_from" name="created_from" value="{{ request('created_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="created_to">{{ __('admin.created_to') }}</label>
                            <input class="form-control" type="date" id="created_to" name="created_to" value="{{ request('created_to') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="qr_code">QR</label>
                            <input class="form-control" id="qr_code" name="qr_code" value="{{ request('qr_code') }}" maxlength="12">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="car_wash_id">{{ __('admin.car_wash') }}</label>
                            <select class="form-select" id="car_wash_id" name="car_wash_id">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($washes as $wash)
                                    <option value="{{ $wash->id }}" @selected((string) request('car_wash_id') === (string) $wash->id)>{{ $wash->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="address">{{ __('admin.address') }}</label>
                            <input class="form-control" id="address" name="address" value="{{ request('address') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="manager_id">{{ __('admin.manager') }}</label>
                            <select class="form-select" id="manager_id" name="manager_id">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($managers as $manager)
                                    <option value="{{ $manager->id }}" @selected((string) request('manager_id') === (string) $manager->id)>{{ $manager->name }} {{ $manager->surname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="service_id">{{ __('admin.services') }}</label>
                            <select class="form-select" id="service_id" name="service_id">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="client">{{ __('admin.client') }}</label>
                            <input class="form-control" id="client" name="client" value="{{ request('client') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="phone">{{ __('admin.phone') }}</label>
                            <input class="form-control" id="phone" name="phone" value="{{ request('phone') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="email">{{ __('admin.email') }}</label>
                            <input class="form-control" id="email" name="email" value="{{ request('email') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="plate">{{ __('admin.plate') }}</label>
                            <input class="form-control" id="plate" name="plate" value="{{ request('plate') }}" maxlength="16">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="brand_id">{{ __('admin.brand') }}</label>
                            <select class="form-select" id="brand_id" name="brand_id">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((string) request('brand_id') === (string) $brand->id)>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="car_type">{{ __('admin.body_type') }}</label>
                            <select class="form-select" id="car_type" name="car_type">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($carTypes as $type)
                                    <option value="{{ $type }}" @selected(request('car_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="package_type">{{ __('admin.package') }}</label>
                            <select class="form-select" id="package_type" name="package_type">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($packageTypes as $type)
                                    <option value="{{ $type }}" @selected(request('package_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label" for="count_washes">{{ __('admin.wash_count') }}</label>
                            <select class="form-select" id="count_washes" name="count_washes">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($washCounts as $count)
                                    <option value="{{ $count }}" @selected((string) request('count_washes') === (string) $count)>{{ $count }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger mt-3 mb-0">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mt-3" style="display:flex;gap:8px;">
                        <button class="btn btn-primary" type="submit">{{ __('admin.apply') }}</button>
                        <button class="btn btn-success" type="submit" name="export" value="1">{{ __('admin.download') }}</button>
                        <a class="btn btn-secondary" href="{{ route('admin.reports.washes') }}">{{ __('admin.reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3 mb-4">
            <div class="card-header">{{ __('admin.found') }}: {{ $appointments->total() }}</div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('admin.client') }}</th>
                        <th>{{ __('admin.phone') }}</th>
                        <th>{{ __('admin.car') }}</th>
                        <th>{{ __('admin.car_wash') }}</th>
                        <th>{{ __('admin.services') }}</th>
                        <th>{{ __('admin.manager') }}</th>
                        <th>{{ __('admin.appointment_datetime') }}</th>
                        <th>{{ __('admin.status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->id }}</td>
                            <td>{{ $appointment->user?->displayName() }}</td>
                            <td>{{ \App\Support\Phone::format($appointment->user?->phone) }}</td>
                            <td>
                                {{ $appointment->car?->model?->brand?->name }}
                                {{ $appointment->car?->model?->name }}
                                @if($appointment->car)
                                    ({{ $appointment->car->plate }})
                                @endif
                            </td>
                            <td>{{ $appointment->washing?->name }}</td>
                            <td>{{ $appointment->servicesList }}</td>
                            <td>{{ trim(($appointment->washing?->manager?->name ?? '').' '.($appointment->washing?->manager?->surname ?? '')) }}</td>
                            <td>{{ $appointment->dateFormatted }} {{ \Carbon\Carbon::parse($appointment->time)->format('H:i') }}</td>
                            <td>{{ \App\Services\WashReportQuery::statusLabel($appointment->approved) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">{{ __('admin.dash_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $appointments->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
