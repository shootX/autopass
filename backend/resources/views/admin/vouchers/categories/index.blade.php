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
                <div>{{ __('admin.voucher_categories') }}</div>
                <div style="display: flex;">
                    <form method="GET">
                        <button style="margin-left: 5px;" class="btn btn-success" name="export" type="submit"><svg class="svg-inline--fa fa-file-excel" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="file-excel" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" data-fa-i2svg=""><path fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V160H256c-17.7 0-32-14.3-32-32V0H64zM256 0V128H384L256 0zM155.7 250.2L192 302.1l36.3-51.9c7.6-10.9 22.6-13.5 33.4-5.9s13.5 22.6 5.9 33.4L221.3 344l46.4 66.2c7.6 10.9 5 25.8-5.9 33.4s-25.8 5-33.4-5.9L192 385.8l-36.3 51.9c-7.6 10.9-22.6 13.5-33.4 5.9s-13.5-22.6-5.9-33.4L162.7 344l-46.4-66.2c-7.6-10.9-5-25.8 5.9-33.4s25.8-5 33.4 5.9z"></path></svg><!-- <i class="fa-solid fa-file-excel"></i> Font Awesome fontawesome.com --></button>
                    </form>
                    <a style="margin-left: 5px;" href="{{route('admin.vouchers.categories.add')}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>

                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.category_name') }}</th>
                        <th scope="col">{{ __('admin.created_at') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($categories as $categorie)
                        <tr>
                            <th scope="row">{{$categorie->id}}</th>
                            <td>{{$categorie->name}}</td>
                            <td>{{$categorie->created_at}}</td>
                            <td>
                                <div class="btn-group">
                                    <a href="{{route('admin.vouchers.categories.edit', $categorie->id)}}" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                    <a class="btn btn-sm btn-danger removeUser" data-id="{{$categorie->id}}"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{$categories->links('pagination::bootstrap-5')}}
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
                    title: I18N.delete_category_title,
                    content: I18N.delete_category_confirm,
                    type: 'red',
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                window.location.href = '/dashboard/vouchers/categories/'+id+'/delete';
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
