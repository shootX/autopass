{{-- A fleet car's body type: confirmed, waiting for the driver's pick, or unknown with a retry; an admin may confirm one. --}}
@if($car->bodyConfirmed())
    <small class="d-block text-muted">{{ \App\Models\BodyType::labelFor($car->car_type) }}</small>
@else
    @if(! empty($car->body_requested))
        {{-- A company car: the company named a type geoapi.ge has not confirmed; a re-check or an admin does. --}}
        <span class="badge bg-warning text-dark">{{ __('admin.fleet_body_status_waiting') }}</span>
        <small class="d-block text-muted">{{ __('admin.fleet_body_requested', ['type' => \App\Models\BodyType::labelFor($car->body_requested)]) }}</small>
    @else
        <span class="badge {{ $car->body_status === 'choose' ? 'bg-info text-dark' : 'bg-warning text-dark' }}">{{ __('admin.fleet_body_status_'.($car->body_status ?: 'unknown')) }}</span>
    @endif
    @if($car->body_options)
        <small class="d-block text-muted">{{ __('admin.fleet_body_options', ['types' => collect($car->body_options)->map(fn ($key) => \App\Models\BodyType::labelFor($key))->implode(', ')]) }}</small>
    @endif
    @if($car->body_incomplete)
        <small class="d-block text-muted">{{ __('admin.fleet_body_incomplete') }}</small>
    @endif
    @if($car->body_status !== 'choose' || ! empty($car->body_requested))
        <form method="POST" action="{{ $carBase.'/'.$car->id.'/body-check' }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-link p-0">{{ __('admin.fleet_body_check') }}</button></form>
    @endif
@endif
{{-- The driver's app car has another confirmed type (activation_error body_conflict): an admin sets the right one. --}}
@if(! empty($adminBody) && (! $car->bodyConfirmed() || $car->activation_error === 'body_conflict'))
    <form method="POST" action="{{ $carBase.'/'.$car->id.'/body' }}" class="d-flex gap-1 mt-1">
        @csrf
        <select name="body_type" class="form-control form-control-sm" required>
            <option value="">{{ __('admin.fleet_select_body') }}</option>
            @foreach($bodyOptions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('admin.fleet_body_confirm') }}</button>
    </form>
@endif
