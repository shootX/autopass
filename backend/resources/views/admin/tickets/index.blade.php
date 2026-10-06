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
                <div>{{ __('admin.tickets') }}</div>
                <div style="display: flex;">
                    <form method="GET">
                        <button style="margin-left: 5px;" class="btn btn-success" name="export" type="submit"><svg class="svg-inline--fa fa-file-excel" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="file-excel" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" data-fa-i2svg=""><path fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V160H256c-17.7 0-32-14.3-32-32V0H64zM256 0V128H384L256 0zM155.7 250.2L192 302.1l36.3-51.9c7.6-10.9 22.6-13.5 33.4-5.9s13.5 22.6 5.9 33.4L221.3 344l46.4 66.2c7.6 10.9 5 25.8-5.9 33.4s-25.8 5-33.4-5.9L192 385.8l-36.3 51.9c-7.6 10.9-22.6 13.5-33.4 5.9s-13.5-22.6-5.9-33.4L162.7 344l-46.4-66.2c-7.6-10.9-5-25.8 5.9-33.4s25.8-5 33.4 5.9z"></path></svg><!-- <i class="fa-solid fa-file-excel"></i> Font Awesome fontawesome.com --></button>
                    </form>
                    <a style="margin-left: 5px;" href="{{route('admin.tickets.add')}}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ __('admin.add') }}</a>

                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">{{ __('admin.name') }}</th>
                        <th scope="col">{{ __('admin.description') }}</th>
                        <th scope="col">{{ __('admin.photo') }}</th>
                        <th scope="col">{{ __('admin.price') }}</th>
                        <th scope="col">{{ __('admin.date_until') }}</th>
                        <th scope="col">{{ __('admin.quantity') }}</th>
                        <th scope="col">{{ __('admin.sold_tickets') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($tickets as $ticket)
                        <tr>
                            <th scope="row">{{$ticket->id}}</th>
                            <td>{{$ticket->name}}</td>
                            <td>{{$ticket->description}}</td>
                            <td>
                                <a href="{{$ticket->photo}}" data-lightbox="gallery" data-title="{{$ticket->name}}">
                                    <img src="{{$ticket->photo}}" width="100">
                                </a>
                            </td>
                            <td>{{ __('admin.points', ['count' => $ticket->price]) }}</td>
                            <td>{{\Carbon\Carbon::parse($ticket->date_to)->format('d.m.Y')}}</td>
                            <td>
                                @if($ticket->count)
                                    {{ __('admin.pcs', ['count' => $ticket->count]) }}
                                @else
                                    {{ __('admin.unlimited') }}
                                @endif
                            </td>
                            <td>
                                {{ __('admin.pcs', ['count' => \App\Models\UserTicket::where('ticket_id', $ticket->id)->count()]) }}
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a data-link="{{route('admin.tickets.export', $ticket)}}" class="btn btn-sm btn-success btnExport"><i class="fa-solid fa-file-excel"></i> {{ __('admin.export') }}</a>
                                    <a href="{{route('admin.tickets.edit', $ticket)}}" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i></a>
                                    <a class="btn btn-sm btn-danger removeUser" data-id="{{$ticket->id}}"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{$tickets->links('pagination::bootstrap-5')}}
            </div>
        </div>

    </div>
@endsection

@push('js')
    <script>
        $(document).ready(function () {

            $(".btnExport").click(function(){
               window.location.href = $(this).data('link');
               return;
            });

            $('.removeUser').on('click', function () {
                let id = $(this).data('id');
                $.confirm({
                    title: I18N.delete_ticket_title,
                    content: I18N.delete_ticket_confirm,
                    type: 'red',
                    buttons: {
                        yes: { text: I18N.yes,
                            btnClass: 'btn-red',
                            action: function () {
                                window.location.href = '/dashboard/tickets/'+id+'/delete';
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
