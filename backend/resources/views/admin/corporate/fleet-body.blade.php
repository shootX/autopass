{{-- A fleet car's body type: confirmed, waiting for the driver's pick, or unknown with a retry; an admin may confirm one. --}}
@if($car->bodyConfirmed())
    <small class="d-block text-muted">{{ \App\Models\BodyType::labelFor($car->car_type) }}</small>
@else
    <span class="badge {{ $car->body_status === 'choose' ? 'bg-info text-dark' : 'bg-warning text-dark' }}">{{ __('admin.fleet_body_status_'.($car->body_status ?: 'unknown')) }}</span>
    @if($car->body_options)
        <small class="d-block text-muted">{{ __('admin.fleet_body_options', ['types' => collect($car->body_options)->map(fn ($key) => \App\Models\BodyType::labelFor($key))->implode(', ')]) }}</small>
    @endif
    @if($car->body_incomplete)
        <small class="d-block text-muted">{{ __('admin.fleet_body_incomplete') }}</small>
    @endif
    @if($car->body_status !== 'choose')
        <form method="POST" action="{{ $carBase.'/'.$car->id.'/body-check' }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-link p-0">{{ __('admin.fleet_body_check') }}</button></form>
    @endif
    @if(! empty($adminBody))
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
@endif
