@extends('partner.layout')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4">
            <div class="card-header">{{ __('admin.wash_report') }}</div>
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label" for="date_from">{{ __('admin.date_from') }}</label>
                            <input class="form-control" type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="date_to">{{ __('admin.date_to') }}</label>
                            <input class="form-control" type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="plate">{{ __('admin.plate') }}</label>
                            <select class="form-select" id="plate" name="plate">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($cars as $car)
                                    <option value="{{ $car->plate }}" @selected(request('plate') === $car->plate)>{{ \App\Services\FleetCars::formatPlate($car->plate) }} · {{ $car->brand }} {{ $car->model }}</option>
                                @endforeach
                            </select>
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
                        <div class="col-md-2">
                            <label class="form-label" for="approved">{{ __('admin.status') }}</label>
                            <select class="form-select" id="approved" name="approved">
                                <option value="">{{ __('admin.all') }}</option>
                                <option value="0" @selected(request('approved') === '0')>{{ __('admin.pending') }}</option>
                                <option value="1" @selected(request('approved') === '1')>{{ __('admin.confirmed') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3" style="display:flex;gap:8px;">
                        <button class="btn btn-primary" type="submit">{{ __('admin.apply') }}</button>
                        <button class="btn btn-success" type="submit" name="export" value="1">{{ __('admin.download') }}</button>
                        <a class="btn btn-secondary" href="{{ route('partner.reports') }}">{{ __('admin.reset') }}</a>
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
                        <th>{{ __('admin.appointment_datetime') }}</th>
                        <th>{{ __('admin.plate') }}</th>
                        <th>{{ __('admin.car') }}</th>
                        <th>{{ __('admin.car_wash') }}</th>
                        <th>{{ __('admin.services') }}</th>
                        <th>{{ __('admin.status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->dateFormatted }} {{ \Carbon\Carbon::parse($appointment->time)->format('H:i') }}</td>
                            <td>{{ $appointment->car?->plate }}</td>
                            <td>{{ $appointment->car?->model?->brand?->name }} {{ $appointment->car?->model?->name }}</td>
                            <td>{{ $appointment->washing?->name }}</td>
                            <td>{{ $appointment->servicesList }}</td>
                            <td>{{ \App\Services\WashReportQuery::statusLabel($appointment->approved) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="umami-empty">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $appointments->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
