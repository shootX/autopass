@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">
                {{ session()->get('message') }}
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                @php($who = $client->titleName())
                <div>{{ $who !== '' ? __('admin.client_cars', ['name' => $who]) : __('admin.vehicles') }}</div>
                <a href="{{route('admin.сlients.add_car', $client->id)}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <th scope="col">ID</th>
                    <th scope="col">{{ __('admin.brand') }}</th>
                    <th scope="col">{{ __('admin.model') }}</th>
                    <th scope="col">{{ __('admin.body') }}</th>
                    <th scope="col">{{ __('admin.plate') }}</th>
                    <th scope="col">{{ __('admin.active_package') }}</th>
                    <th scope="col">{{ __('admin.car_added_at') }}</th>
                    <th scope="col">{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                    @forelse($userCars as $car)
                        <tr>
                            <td>{{$car->id}}</td>
                            <td>{{$car->model->brand->name}}</td>
                            <td>{{$car->model->name}}</td>
                            <td>{{$car->model->type}}</td>
                            <td>{{$car->plate}}</td>
                            <td>
                                @if($car->package)
                                    <span class="badge bg-success">{{ __('admin.car_package_active', ['used' => $car->package->used_washes, 'total' => $car->package->package->count_washes, 'date' => \Carbon\Carbon::parse($car->package->end_date)->format('d.m.Y - H:i')]) }}</span>
                                @else
                                    <span class="badge bg-danger">{{ __('admin.no_package') }}</span>
                                @endif
                            </td>
                            <td>{{\Carbon\Carbon::parse($car->created_at)->format('d.m.Y - H:i')}}</td>


                            <td>
                                <div class="btn-group">
{{--                                    <a class="btn btn-sm btn-dark" href="{{route('admin.clients.ban', $client)}}"><i class="fas fa-ban"></i></a>--}}
{{--                                    <a class="btn btn-sm btn-primary" href="{{route('admin.clients.edit', $client)}}"><i class="fas fa-edit"></i></a>--}}
                                    <a class="btn btn-sm btn-danger remove" data-id="{{$car->id}}"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{$userCars->links('pagination::bootstrap-5')}}
            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function () {

            $('.remove').on('click', function () {
                let id = $(this).data('id');
                $.confirm({
                    title: I18N.delete_car_title,
                    content: I18N.delete_car_confirm,
                    type: 'red',
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                postAction('/dashboard/clients/{{$client->id}}/cars/'+id+'/delete');
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
