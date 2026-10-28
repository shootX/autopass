@extends('admin.layout.app')

@push('css')
    <style>
        td{
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">

        @include('admin.partials.alerts')

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.models_of', ['name' => $brand->name]) }}</div>
                <a href="{{route('admin.car_brand_models_add', $brand->id)}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.model_name') }}</th>
                        <th scope="col">{{ __('admin.body_type') }}</th>
                    </thead>
                    <tbody>
                    @forelse($models as $model)
                        <tr>
                            <td>{{$model->id}}</td>
                            <td colspan="2">
                                <form action="{{ route('admin.car_brand_models_save', [$brand->id, $model->id]) }}" method="post" class="d-flex gap-2">@csrf
                                    <input name="name" value="{{ $model->name }}" class="form-control form-control-sm" maxlength="255" required>
                                    <select name="type" class="form-select form-select-sm">
                                        @foreach(\App\Models\BodyType::options() as $key => $label)
                                            <option value="{{ $key }}" @selected($model->type === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-primary">{{ __('admin.save') }}</button>
                                </form>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-danger removeBtn" data-id="{{$model->id}}" href="#"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{$models->links('pagination::bootstrap-5')}}
            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function(){
            $('.removeBtn').click(function(){
                let removeID = $(this).data('id');
                $.confirm({
                    title: I18N.delete_model_title,
                    content: I18N.delete_model_confirm,
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function() {
                                window.deleteRequest('/dashboard/brands/{{$brand->id}}/models/'+removeID+'/delete');
                            }
                        },
                        no: { text: I18N.no,
                            btnClass: 'btn-blue',
                        }
                    }
                });
            });
        });
    </script>
@endpush
