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

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.body_types') }}</div>
                <a href="{{ route('admin.body_types.add') }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.photo') }}</th>
                        <th scope="col">{{ __('admin.body_type_name') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                    @forelse($types as $type)
                        <tr>
                            <td>{{ $type->id }}</td>
                            <td>
                                @if($type->image)
                                    <img src="{{ $type->image }}" alt="" style="height: 36px;">
                                @endif
                            </td>
                            <td>{{ $type->name }}</td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-primary" href="{{ route('admin.body_types.edit', $type->id) }}"><i class="fas fa-edit"></i></a>
                                    <a class="btn btn-sm btn-danger removeBtn" data-id="{{ $type->id }}" href="#"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">{{ __('admin.dash_empty') }}</td></tr>
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
                                postAction('/dashboard/body-types/' + removeID + '/delete');
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
