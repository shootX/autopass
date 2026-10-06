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
                <div>{{ __('admin.reviews_for', ['washing' => $washing->name.' ('.$washing->address.')']) }}</div>
                <a href="{{route('admin.washings.review_add', $washing->id)}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">

                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <td>{{ __('admin.user') }}</td>
                        <td>{{ __('admin.rating') }}</td>
                        <td>{{ __('admin.review_text') }}</td>
                        <td>{{ __('admin.actions') }}</td>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($reviews as $review)
                        <tr>
                            <td>{{$review->id}}</td>
                            <td>{{$review->user->name}} {{$review->user->surname}}</td>
                            <td>
                                <div class="stars text-warning">
                                    @for($i = 1; $i <= $review->stars; $i++)
                                        <i class="fa fa-star"></i>
                                    @endfor
                                    @for($i = 1; $i <= 5 - $review->stars; $i++)
                                        <i class="fa fa-star text-secondary"></i>
                                    @endfor
                                </div>
                            </td>
                            <td>{{$review->text}}</td>
                            <td>
                                <div class="btn-group">
                                    @if($review->status)
                                        <a href="{{route('admin.washings.reviews.toggle', [$washing->id, $review->id])}}"><button class="btn btn-sm btn-dark"><i class="fa-solid fa-eye"></i> {{ __('admin.hide') }}</button></a>
                                    @else
                                        <a href="{{route('admin.washings.reviews.toggle', [$washing->id, $review->id])}}"><button class="btn btn-sm btn-dark"><i class="fa-solid fa-eye"></i> {{ __('admin.show') }}</button></a>
                                    @endif
                                    <a data-id="{{$review->id}}" class="btn btn-sm btn-danger remove"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{$reviews->links('pagination::bootstrap-5')}}
            </div>
        </div>
    </div>

@endsection

@push('js')
    <script>

        $(document).ready(function(){
            $('.remove').click(function(){
                let removeID = $(this).data('id');
                $.confirm({
                    title: I18N.deletion,
                    content: I18N.delete_review_confirm,
                    type: 'red',
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function() {
                                window.location.href = '/dashboard/washings/{{$washing->id}}/reviews/'+removeID+'/delete';
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
