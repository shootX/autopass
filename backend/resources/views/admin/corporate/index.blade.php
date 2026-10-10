@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @if(session()->has('message'))
            <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <div>{{ __('admin.partners') }}</div>
                <a href="{{ route('admin.corporate.add') }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input class="form-control" type="search" name="q" value="{{ $q }}" placeholder="{{ __('admin.corporate_search') }}">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-secondary" type="submit">{{ __('admin.apply') }}</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ __('admin.name') }}</th>
                            <th>{{ __('admin.identification_code') }}</th>
                            <th>{{ __('admin.phone') }}</th>
                            <th>{{ __('admin.fleet') }}</th>
                            <th>{{ __('admin.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($clients as $client)
                            <tr>
                                <th>{{ $client->id }}</th>
                                <td>{{ $client->name }}</td>
                                <td>{{ $client->identification_code }}</td>
                                <td>{{ $client->phone }}</td>
                                <td>{{ $client->cars_count }}</td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-success" href="{{ route('admin.corporate.show', $client) }}">{{ __('admin.fleet') }}</a>
                                        <a class="btn btn-sm btn-primary" href="{{ route('admin.corporate.edit', $client) }}"><i class="fas fa-edit"></i></a>
                                        <a class="btn btn-sm btn-danger remove-corporate" data-id="{{ $client->id }}"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="umami-empty">{{ __('admin.corporate_empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $clients->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $('.remove-corporate').on('click', function () {
            let id = $(this).data('id');
            $.confirm({
                title: I18N.delete_corporate_title,
                content: I18N.delete_corporate_confirm,
                type: 'red',
                buttons: {
                    yes: { text: I18N.yes, btnClass: 'btn-red', action: function () {
                        window.location.href = '/dashboard/corporate/' + id + '/delete';
                    }},
                    no: { text: I18N.no }
                }
            });
        });
    </script>
@endpush
