@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4">
            <div class="card-header">{{ __('admin.qr_wash_report') }}</div>
            <div class="card-body">
                <p class="text-muted">{{ __('admin.qr_wash_report_hint') }}</p>
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
                        <div class="col-md-2">
                            <label class="form-label" for="kind">{{ __('admin.qr_wash_kind') }}</label>
                            <select class="form-select" id="kind" name="kind">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach(\App\Services\QrWashReport::KINDS as $kind)
                                    <option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ __('admin.qr_wash_kind_'.$kind) }}</option>
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
                        <div class="col-md-3">
                            <label class="form-label" for="manager_id">{{ __('admin.manager') }}</label>
                            <select class="form-select" id="manager_id" name="manager_id">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($managers as $manager)
                                    <option value="{{ $manager->id }}" @selected((string) request('manager_id') === (string) $manager->id)>{{ $manager->name }} {{ $manager->surname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="car_type">{{ __('admin.body_type') }}</label>
                            <select class="form-select" id="car_type" name="car_type">
                                <option value="">{{ __('admin.all') }}</option>
                                @foreach($carTypes as $type)
                                    <option value="{{ $type }}" @selected(request('car_type') === $type)>{{ \App\Models\BodyType::labelFor($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="plate">{{ __('admin.plate') }}</label>
                            <input class="form-control" id="plate" name="plate" value="{{ request('plate') }}" maxlength="16">
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
                        <a class="btn btn-secondary" href="{{ route('admin.reports.qr_washes') }}">{{ __('admin.reset') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mt-1">
            @foreach([
                'qr_wash_total' => $totals['washes'],
                'qr_wash_kind_package' => $totals['package'],
                'qr_wash_kind_corporate' => $totals['corporate'],
                'qr_wash_paid' => number_format($totals['paid'], 2).' ₾',
            ] as $label => $value)
                <div class="col-6 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">{{ __('admin.'.$label) }}</div>
                            <div class="fs-4 fw-semibold">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mt-3 mb-4">
            <div class="card-header">{{ __('admin.found') }}: {{ $rows->total() }}</div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>{{ __('admin.qr_wash_time') }}</th>
                        <th>{{ __('admin.qr_wash_kind') }}</th>
                        <th>{{ __('admin.plate') }}</th>
                        <th>{{ __('admin.client') }}</th>
                        <th>{{ __('admin.body_type') }}</th>
                        <th>{{ __('admin.car_wash') }}</th>
                        <th>{{ __('admin.manager') }}</th>
                        <th>{{ __('admin.qr_wash_booking') }}</th>
                        <th>{{ __('admin.qr_wash_paid_on_site') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d.m.Y H:i') }}</td>
                            <td>{{ __('admin.qr_wash_kind_'.$row->kind) }}</td>
                            <td>{{ $row->plate }}</td>
                            <td>{{ \App\Services\QrWashReport::client($row) }}</td>
                            <td>{{ $row->car_type ? \App\Models\BodyType::labelFor($row->car_type) : '' }}</td>
                            <td>{{ $row->wash_name }}</td>
                            <td>{{ \App\Services\QrWashReport::manager($row) }}</td>
                            <td>{{ $row->appointment_id ? '#'.$row->appointment_id : __('admin.qr_wash_walk_in') }}</td>
                            <td>{{ $row->to_pay !== null ? number_format((float) $row->to_pay, 2).' ₾' : '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">{{ __('admin.dash_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $rows->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
