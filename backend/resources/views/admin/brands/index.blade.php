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

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">
                {{ session()->get('message') }}
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.car_brands') }}</div>
                <a href="{{route('admin.add_car_brand')}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <th scope="col">ID</th>
                    <th scope="col">{{ __('admin.brand_name') }}</th>
                    <th scope="col">{{ __('admin.models') }}</th>
                    </thead>
                    <tbody>
                    @forelse($brands as $brand)
                        <tr class="brand-row" data-id="{{$brand->id}}" data-name="{{$brand->name}}" style="cursor: pointer;">
                            <td>{{$brand->id}}</td>
                            <td>{{$brand->name}}</td>
                            <td><a class="btn btn-sm btn-secondary modelsBtn" data-id="{{$brand->id}}" data-name="{{$brand->name}}" href="{{route('admin.car_brand_models', $brand->id)}}">
                                    <i class="fas fa-car"></i>
                                    {{$brand->models()->count()}}</a></td>
                            <td>
                                <div class="btn-group">
{{--                                    <a class="btn btn-sm btn-primary" href="{{route('admin.packages.edit', $brand->id)}}"><i class="fas fa-edit"></i></a>--}}
                                    <a class="btn btn-sm btn-danger removeBtn" data-id="{{$brand->id}}" href="#"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{$brands->links('pagination::bootstrap-5')}}
            </div>
        </div>

        <div class="card mb-4 d-none" id="modelsCard">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div id="modelsTitle">{{ __('admin.models') }}</div>
                <a href="#" id="addModelBtn" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <th scope="col">ID</th>
                    <th scope="col">{{ __('admin.model_name') }}</th>
                    <th scope="col">{{ __('admin.body_type') }}</th>
                    <th></th>
                    </thead>
                    <tbody id="modelsBody"></tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        function loadModels(brandId, brandName) {
            $('.brand-row').removeClass('table-active');
            $('.brand-row[data-id="' + brandId + '"]').addClass('table-active');

            $.get('/dashboard/brands/' + brandId + '/models/list', function (data) {
                let rows = '';
                data.models.forEach(function (model) {
                    rows += '<tr>' +
                        '<td>' + model.id + '</td>' +
                        '<td>' + $('<div>').text(model.name).html() + '</td>' +
                        '<td>' + $('<div>').text(model.type).html() + '</td>' +
                        '<td><a class="btn btn-sm btn-danger modelRemoveBtn" data-id="' + model.id + '" href="#"><i class="fas fa-trash"></i></a></td>' +
                        '</tr>';
                });
                if (!rows) {
                    rows = '<tr><td colspan="4">' + I18N.no_models + '</td></tr>';
                }
                $('#modelsTitle').text(I18N.models + ' ' + brandName);
                $('#addModelBtn').attr('href', '/dashboard/brands/' + brandId + '/models/add');
                $('#modelsBody').html(rows).data('brand', brandId);
                $('#modelsCard').removeClass('d-none');
            });
        }

        $(document).ready(function(){
            $('.brand-row').on('click', function (e) {
                if ($(e.target).closest('.removeBtn').length) {
                    return;
                }
                loadModels($(this).data('id'), $(this).data('name'));
            });

            $('.modelsBtn').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                loadModels($(this).data('id'), $(this).data('name'));
            });

            $('#modelsBody').on('click', '.modelRemoveBtn', function (e) {
                e.preventDefault();
                let removeID = $(this).data('id');
                let brandId = $('#modelsBody').data('brand');
                $.confirm({
                    title: I18N.delete_model_title,
                    content: I18N.delete_model_confirm,
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                window.location.href = '/dashboard/brands/' + brandId + '/models/' + removeID + '/delete';
                            }
                        },
                        no: { text: I18N.no,
                            btnClass: 'btn-blue',
                        }
                    }
                });
            });

            $('.removeBtn').click(function(e){
                e.stopPropagation();
                let removeID = $(this).data('id');
                $.confirm({
                    title: I18N.delete_brand_title,
                    content: I18N.delete_brand_confirm,
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function() {
                                window.location.href = '/dashboard/brands/'+removeID+'/delete';
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
