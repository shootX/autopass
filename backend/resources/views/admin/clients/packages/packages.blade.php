@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">
                {{ session()->get('message') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger mt-4">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{$error}}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.user_packages', ['name' => trim($client->name.' '.$client->surname)]) }}</div>
                <a href="{{route('admin.clients.add_package', $client->id)}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add_package') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.package') }}</th>
                        <th scope="col">{{ __('admin.car') }}</th>
                        <th scope="col">{{ __('admin.start_date') }}</th>
                        <th scope="col">{{ __('admin.end_date') }}</th>
                        <th scope="col">{{ __('admin.washes_used') }}</th>
                        <th scope="col">{{ __('admin.auto_renew') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                        @foreach($userPackages as $pack)
                            <tr>
                                <td>{{$pack->id}}</td>
                                <td>{{$pack->package->car_type}}</td>
                                <td>{{$pack->car->model->brand->name}} {{$pack->car->model->name}} ({{$pack->car->plate}})</td>
                                <td>{{\Carbon\Carbon::parse($pack->start_date)->format('d.m.Y - H:i')}}</td>
                                <td>{{\Carbon\Carbon::parse($pack->end_date)->format('d.m.Y - H:i')}}</td>
                                <td>{{$pack->used_washes}}/{{$pack->number_of_washes}}</td>
                                <td>
                                    <button class="btn btn-sm btn-dark" type="button" data-id="{{$pack->id}}" data-bs-toggle="modal" data-bs-target="#setRenewModal">
                                        <i class="fa fa-refresh"></i>
                                    </button>
                                    @if($pack->rectoken)
                                        <span class="badge bg-success">{{ __('admin.active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ __('admin.inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-primary" href="{{route('admin.clients.packages.delete', [$client->id, $pack->id])}}"><i class="fas fa-edit"></i></a>
                                        <a class="btn btn-sm btn-danger remove" data-id="{{$pack->id}}"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{$userPackages->links('pagination::bootstrap-5')}}
            </div>
        </div>

    </div>
@endsection

@push('modals')
    <div class="modal fade" id="setRenewModal" tabindex="-1" aria-labelledby="setRenewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="setRenewModalLabel">{{ __('admin.renew_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{route('admin.clients.renew_package', $client->id)}}" method="POST">
                    @csrf
                    <input type="hidden" name="package_id" id="package_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="renew">{{ __('admin.renew_until') }}</label>
                            <input type="date" min="{{\Carbon\Carbon::now()->addDay(1)->format('Y-m-d')}}" class="form-control" id="renew" name="date" placeholder="{{ __('admin.enter_date') }}" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('js')
    <script>
        $(document).ready(function () {

            $('.remove').on('click', function () {
                let id = $(this).data('id');
                $.confirm({
                    title: I18N.delete_user_package_title,
                    content: @json(__('admin.delete_user_package_confirm', ['name' => trim($client->name.' '.$client->surname)])),
                    type: 'red',
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                window.location.href = '/dashboard/clients/{{$client->id}}/packages/'+id+'/delete';
                            }
                        },
                        no: { text: I18N.no,
                            btnClass: 'btn-default',
                        }
                    }
                });
            });

            $('#setRenewModal').on('show.bs.modal', function (event) {
                let button = $(event.relatedTarget); // Button that triggered the modal
                let packageId = button.data('id'); // Extract info from data-* attributes
                let modal = $(this);
                modal.find('#package_id').val(packageId);
            });

        });
    </script>
@endpush
