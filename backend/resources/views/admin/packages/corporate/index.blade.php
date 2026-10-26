@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.packages.tabs')

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>{{ __('admin.cp_tab_corporate') }}</div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.packages.corporate.add', array_filter(['company' => $company])) }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.cp_type_washes') }}</a>
                    <a href="{{ route('admin.packages.corporate.add', array_filter(['company' => $company, 'type' => 'discount'])) }}" class="btn btn-outline-primary"><i class="fa fa-plus"></i> {{ __('admin.cp_type_discount') }}</a>
                </div>
            </div>
            <div class="card-body table-responsive">
                <form method="get" class="row g-2 mb-2">
                    <div class="col-md-4">
                        <select class="form-select" name="company" onchange="this.form.submit()" aria-label="{{ __('admin.cp_company') }}">
                            <option value="">{{ __('admin.cp_all_companies') }}</option>
                            @foreach($companies as $item)
                                <option value="{{ $item->id }}" @selected($company === $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                <small class="d-block mb-2">{{ __('admin.cp_hint') }}</small>
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>{{ __('admin.cp_company') }}</th>
                        <th>{{ __('admin.cw_package_title') }}</th>
                        <th>{{ __('admin.cp_type') }}</th>
                        <th>{{ __('admin.cp_terms') }}</th>
                        <th>{{ __('admin.cw_package_price') }}</th>
                        <th>{{ __('admin.status') }}</th>
                        <th>{{ __('admin.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>
                                @if($package->client)
                                    <a href="{{ route('admin.corporate.show', $package->client) }}#washes">{{ $package->client->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $package->title }}</td>
                            <td>{{ __('admin.cp_type_'.$package->type) }}</td>
                            <td>@include('admin.packages.corporate.terms')</td>
                            <td>{{ $package->price !== null ? number_format((float) $package->price, 2).' ₾' : '—' }}</td>
                            <td><span class="badge {{ $package->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $package->is_active ? __('admin.active') : __('admin.cw_package_inactive') }}</span></td>
                            <td>
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.packages.corporate.edit', $package) }}" title="{{ __('admin.edit') }}"><i class="fas fa-edit"></i></a>
                                <form method="post" action="{{ route('admin.packages.corporate.delete', $package) }}" class="d-inline" onsubmit="return confirm(@json(__('admin.cp_delete_confirm')))">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="umami-empty">{{ __('admin.cw_packages_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $packages->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
