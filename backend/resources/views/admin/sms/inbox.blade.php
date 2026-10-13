@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        <div class="card mt-4 mb-4">
            <div class="card-header">სატესტო SMS</div>
            <div class="card-body">
                <p>SMS-ის სატესტო რეჟიმია — შეტყობინება ტელეფონზე არ იგზავნება.</p>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>ნომერი</th>
                            <th>დანიშნულება</th>
                            <th>შეიქმნა</th>
                            <th>ვადა</th>
                            <th>ტექსტი</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($messages as $message)
                            <tr>
                                <td>{{ $message['phone_mask'] }}</td>
                                <td>{{ $message['purpose'] }}</td>
                                <td>{{ $message['created_at'] }}</td>
                                <td>{{ $message['expires_at'] }}</td>
                                <td>{{ $message['text'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">შეტყობინება არ არის</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
