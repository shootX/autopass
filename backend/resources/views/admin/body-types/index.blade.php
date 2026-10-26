@extends('admin.layout.app')

@push('css')
    <style>
        td {
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">
                {{ session()->get('message') }}
            </div>
        @endif

        @if(session()->has('error'))
            <div class="alert alert-danger mt-4">
                {{ session()->get('error') }}
            </div>
        @endif

        @if($unpriced->isNotEmpty())
            <div class="alert alert-warning mt-4">
                {{ __('admin.body_type_unpriced') }}
                @foreach($unpriced as $type => $count)
                    <strong>{{ \App\Models\BodyType::labelFor($type) }}</strong> ({{ $count }})@if(! $loop->last), @endif
                @endforeach
            </div>
        @endif

        @if($unknown->isNotEmpty())
            <div class="alert alert-info mt-4">
                {{ __('admin.body_type_unknown') }}
                @foreach($unknown as $name => $count)
                    <strong>{{ $name }}</strong> ({{ $count }})@if(! $loop->last), @endif
                @endforeach
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.body_types') }}</div>
                <a href="{{ route('admin.body_types.add') }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.body_type_name') }}</th>
                        <th scope="col">{{ __('admin.body_type_key') }}</th>
                        <th scope="col">{{ __('admin.body_type_cars') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                    @forelse($types as $type)
                        <tr>
                            <td>{{ $type->id }}</td>
                            <td>{{ $type->label() }}</td>
                            <td class="text-muted">{{ $type->name }}</td>
                            <td>{{ $cars[$type->name] ?? 0 }}</td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-primary" href="{{ route('admin.body_types.edit', $type->id) }}"><i class="fas fa-edit"></i></a>
                                    <a class="btn btn-sm btn-danger removeBtn" data-id="{{ $type->id }}" href="#"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $types->links('pagination::bootstrap-5') }}
            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function () {
            $('.removeBtn').click(function () {
                let removeID = $(this).data('id');
                $.confirm({
                    title: I18N.delete_body_type_title,
                    content: I18N.delete_body_type_confirm,
                    buttons: {
                        yes: {
                            text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                window.deleteRequest('/dashboard/body-types/' + removeID + '/delete');
                            }
                        },
                        no: {
                            text: I18N.no,
                            btnClass: 'btn-blue',
                        }
                    }
                });
            });
        });
    </script>
@endpush
