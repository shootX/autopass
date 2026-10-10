@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">
                {{ session()->get('message') }}
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex; justify-content: space-between;">
                <div>{{ __('admin.clients') }}</div>
                <div>
                    <form method="GET" style="display: flex;">
                        <input style="max-width: 275px;" value="{{ request('search') }}" class="form-control" type="text" name="search" placeholder="{{ __('admin.search') }}">
                        <button style="margin-left: 5px;" class="btn btn-secondary" type="submit">{{ __('admin.search') }}</button>
                        <button style="margin-left: 5px;" class="btn btn-success" name="export" type="submit"><i class="fa-solid fa-file-excel"></i></button>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        @php
                            $sort = request('sort', 'id');
                            $dir = request('dir', 'desc');
                            $sortUrl = function (string $column) use ($sort, $dir) {
                                return request()->fullUrlWithQuery([
                                    'sort' => $column,
                                    'dir' => $sort === $column && $dir === 'desc' ? 'asc' : 'desc',
                                ]);
                            };
                        @endphp
                        <th scope="col"><a href="{{ $sortUrl('id') }}">ID</a></th>
                        <th scope="col">{{ __('admin.full_name') }}</th>
                        <th scope="col">Email</th>
                        <th scope="col">{{ __('admin.phone') }}</th>
                        <th scope="col">{{ __('admin.sex') }}</th>
                        <th scope="col">{{ __('admin.birth_date') }}</th>
                        <th scope="col"><a href="{{ $sortUrl('created_at') }}">{{ __('admin.registered_at') }}</a></th>
                        <th scope="col">{{ __('admin.cars_count') }}</th>
                        <th scope="col">{{ __('admin.active_packages') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                    @forelse($clients as $client)
                        <tr @if($client->ban) class="table-danger" @endif >
                            <td>{{$client->id}}</td>
                            <td>{{$client->name}} {{$client->surname}}</td>
                            <td><a href="mailto:{{$client->email}}">{{$client->email}}</a></td>
                            <td><a href="tel:{{ \App\Support\Phone::digits($client->phone) }}">{{ \App\Support\Phone::format($client->phone) }}</a></td>
                            <td>{{ $client->sex === 'male' ? __('admin.male') : ($client->sex === 'female' ? __('admin.female') : '—') }}</td>
                            <td>{{$client->date_of_birth}}</td>
                            <td>{{$client->created_at}}</td>
                            <td>
                                <a class="btn btn-sm btn-secondary" href="{{route('admin.clients.cars', $client->id)}}">
                                <i class="fas fa-car"></i>
                                    {{$client->cars->count()}}
                                </a>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-success" href="{{route('admin.clients.packages', $client->id)}}">
                                <i class="fas fa-box"></i>
                                    {{$client->packages->count()}}
                                </a>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-dark" href="{{route('admin.clients.ban', $client)}}"><i class="fas fa-ban"></i></a>
                                    <a class="btn btn-sm btn-primary" href="{{route('admin.clients.edit', $client)}}"><i class="fas fa-edit"></i></a>
                                    <a class="btn btn-sm btn-danger removeUser" data-id="{{$client->id}}"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{$clients->links('pagination::bootstrap-5')}}
            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function () {

            $('.removeUser').on('click', function () {
                let id = $(this).data('id');
                $.confirm({
                    title: I18N.delete_client_title,
                    content: I18N.delete_client_confirm,
                    type: 'red',
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                window.location.href = '/dashboard/clients/'+id+'/delete';
                            }
                        },
                        no: { text: I18N.no,
                            btnClass: 'btn-default',
                        }
                    }
                });
            });

        });
    </script>
@endpush
