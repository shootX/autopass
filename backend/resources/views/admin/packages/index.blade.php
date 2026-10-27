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
        @include('admin.packages.tabs')

        @if(session()->has('message'))
            <div class="alert alert-success mt-4">
                {{ session()->get('message') }}
            </div>
        @endif

        <div class="card mt-4 mb-4">
            <div class="card-header" style="display: flex;justify-content: space-between;align-items: baseline;">
                <div>{{ __('admin.packages') }}</div>
                <a href="{{route('admin.packages.add')}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.package_type') }}</th>
                        <th scope="col">{{ __('admin.body_type') }}</th>
                        <th scope="col">{{ __('admin.wash_count') }}</th>
                        <th scope="col">{{ __('admin.price_1') }}</th>
                        <th scope="col">{{ __('admin.price_3') }}</th>
                        <th scope="col">{{ __('admin.price_6') }}</th>
                        <th scope="col">{{ __('admin.price_12') }}</th>
                    </thead>
                    <tbody>
                    @forelse($packages as $pack)
                        <tr>
                            <td>{{$pack->id}}</td>
                            <td>{{ $pack->isDiscount() ? __('admin.package_type_discount') : __('admin.package_type_washes') }}</td>
                            <td>{{ \App\Models\BodyType::labelFor($pack->car_type) }}</td>
                            @if($pack->isDiscount())
                                <td>{{ $pack->count_washes > 0 ? $pack->count_washes : __('admin.discount_unlimited') }}</td>
                                <td colspan="4">{{ __('admin.discount_package_row', ['full' => $pack->full_price, 'percent' => $pack->percent, 'pays' => $pack->toPay(), 'price' => $pack->priceForMonth(1)?->price ?? '—', 'days' => \App\Models\Package::DISCOUNT_DAYS]) }}</td>
                            @else
                                <td>{{$pack->count_washes}}</td>
                                <td>{{ $pack->priceForMonth(1)?->price ?? '—' }} ₾</td>
                                <td>{{ $pack->priceForMonth(3)?->price ?? '—' }} ₾</td>
                                <td>{{ $pack->priceForMonth(6)?->price ?? '—' }} ₾</td>
                                <td>{{ $pack->priceForMonth(12)?->price ?? '—' }} ₾</td>
                            @endif
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-primary" href="{{route('admin.packages.edit', $pack->id)}}"><i class="fas fa-edit"></i></a>
                                    <a class="btn btn-sm btn-danger removeBtn" data-id="{{$pack->id}}" href="#"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">{{ __('admin.dash_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{$packages->links('pagination::bootstrap-5')}}
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
                    title: I18N.delete_package_title,
                    content: I18N.delete_package_confirm,
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function() {
                                window.deleteRequest('/dashboard/packages/'+removeID+'/delete');
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
