@extends('admin.layout.app')

@push('css')
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map {
            height: 400px;
            width: 100%;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.edit_ticket') }}</div>
            <div class="card-body table-responsive">

                <form action="{{route('admin.tickets.save', $ticket->id)}}" enctype="multipart/form-data" method="post">
                    @csrf


                    <x-form-input value="{{old('name', $ticket->name)}}" required type="text" title="{{ __('admin.name') }}" name="name"/>
                    <x-form-input value="{{old('description', $ticket->description)}}" required type="textarea" rows="5" title="{{ __('admin.description') }}" name="description"/>

                    <div class="form-group mt-3">
                        <label for="carType">{{ __('admin.photo') }}</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpg, image/jpeg, image/png">
                    </div>

                    <x-form-input value="{{old('price', $ticket->price)}}" required type="number" title="{{ __('admin.cost') }}" name="price"/>
                    <x-form-input value="{{old('date', $ticket->date_to)}}" required type="date" title="{{ __('admin.date_until') }}" name="date_to"/>
                    <x-form-input value="{{old('count', $ticket->count)}}" type="number" title="{{ __('admin.quantity') }}" placeholder="{{ __('admin.unlimited_hint') }}" name="count"/>


                    <button type="submit" class="btn btn-primary mt-4">{{ __('admin.save') }}</button>
                    @if($errors->any())
                        <div class="alert alert-danger mt-4">
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{$error}}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection
