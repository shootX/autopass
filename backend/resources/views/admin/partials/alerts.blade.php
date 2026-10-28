@if(session()->has('message'))
    <div class="alert alert-success mt-4">{{ session()->get('message') }}</div>
@endif
@if(session()->has('error'))
    <div class="alert alert-danger mt-4">{{ session()->get('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger mt-4">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
