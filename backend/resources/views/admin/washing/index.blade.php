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

        @if(session()->has('success'))
            <div class="alert alert-success mt-4">
                {{ session()->get('success') }}
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.washings') }}</div>
                <div style="display: flex;">
                    <form method="GET">
                        <button style="margin-left: 5px;" class="btn btn-success" name="export" type="submit"><svg class="svg-inline--fa fa-file-excel" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="file-excel" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" data-fa-i2svg=""><path fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V160H256c-17.7 0-32-14.3-32-32V0H64zM256 0V128H384L256 0zM155.7 250.2L192 302.1l36.3-51.9c7.6-10.9 22.6-13.5 33.4-5.9s13.5 22.6 5.9 33.4L221.3 344l46.4 66.2c7.6 10.9 5 25.8-5.9 33.4s-25.8 5-33.4-5.9L192 385.8l-36.3 51.9c-7.6 10.9-22.6 13.5-33.4 5.9s-13.5-22.6-5.9-33.4L162.7 344l-46.4-66.2c-7.6-10.9-5-25.8 5.9-33.4s25.8-5 33.4 5.9z"></path></svg><!-- <i class="fa-solid fa-file-excel"></i> Font Awesome fontawesome.com --></button>
                    </form>
                    <a style="margin-left: 5px;" href="{{route('admin.washings.add')}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>

                </div>
            </div>
            <div class="card-body table-responsive">

                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <td>{{ __('admin.name') }}</td>
                        <td>{{ __('admin.address') }}</td>
                        <td>{{ __('admin.work_hours') }}</td>
                        <td>{{ __('admin.manager') }}</td>
                        <td>{{ __('admin.reviews') }}</td>
                        <td>{{ __('admin.actions') }}</td>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($washings as $wash)
                        <tr>
                            <td>{{$wash->id}}</td>
                            <td>{{$wash->name}}</td>
                            <td>{{$wash->address}}</td>
                            <td>{{$wash->work_time_start}} - {{$wash->work_time_end}}</td>
                            <td>
                                <button class="btn btn-sm btn-dark" type="button" data-id="{{$wash->id}}" data-bs-toggle="modal" data-bs-target="#setManagerModal">
                                    <i class="fa fa-refresh"></i>
                                </button>
                                {{$wash->manager->name}} {{$wash->manager->surname}}
                            </td>
                            <td>
                                <a href="{{route('admin.washings.reviews', $wash->id)}}" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-comments"></i> {{$wash->reviews()->count()}}
                                </a>
                            <td>
                                <div class="btn-group">
                                    <a href="{{route('admin.washings.edit', $wash->id)}}" class="btn btn-sm btn-primary"><i class="fas fa-pencil"></i></a>
                                    <a data-id="{{$wash->id}}" class="btn btn-sm btn-danger removeBtn"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{$washings->links('pagination::bootstrap-5')}}
            </div>
        </div>
    </div>

@endsection

@push('modals')
    <div class="modal fade" id="setManagerModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('admin.set_manager') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('admin.close') }}"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="washing_id" name="washing_id" value="0">
                    <div class="form-group">
                        <label for="selectManager">{{ __('admin.choose_manager') }}</label>
                        <select class="form-control" id="selectManager" name="manager_id"></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="saveManager" type="button" class="btn btn-primary">{{ __('admin.save') }}</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('admin.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('js')
    <script>
        $('#setManagerModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var recipient = button.data('id');
            $("#washing_id").val(recipient);
        });

        $('#selectManager').select2({
            theme: 'bootstrap-5',
            placeholder: I18N.type_to_search,
            minimumInputLength: 3, // начинать поиск с 2 символов
            dropdownParent: $('.modal-body'),
            ajax: {
                url: '/dashboard/search_managers',
                dataType: 'json',
                delay: 250,
                minimumInputLength: 3,
                data: function (params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                }
            },
            placeholder: I18N.select_value,
            minimumInputLength: 2
        });

        $("#saveManager").click(function () {
            $("#setManagerModal").modal('hide');
            let manager_id = $("#selectManager").val();
            let washing_id = $("#washing_id").val();

            $.post('/dashboard/set_manager_washing', {manager_id: manager_id, washing_id: washing_id}, function (data) {
                $.confirm({
                    title: I18N.success,
                    content: I18N.manager_set,
                    buttons: {
                        ok: { text: I18N.ok,
                            action: function() {
                                location.reload();
                            }
                        }
                    }
                });
            });
        });

        $(document).ready(function(){
            $('.removeBtn').click(function(){
                let removeID = $(this).data('id');
                $.confirm({
                    title: I18N.deletion,
                    content: I18N.delete_washing,
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function() {
                                window.location.href = '/dashboard/washings/'+removeID+'/delete';
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
