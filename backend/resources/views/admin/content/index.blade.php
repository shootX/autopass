@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.partials.alerts')

        <div class="card mt-4 mb-4">
            <div class="card-header">{{ __('admin.content_faq') }}</div>
            <div class="card-body">
                @foreach($faqs as $faq)
                    <form action="{{ route('admin.content.faq.save', $faq) }}" method="post" class="border rounded p-3 mb-3">
                        @csrf
                        <input name="question" class="form-control mb-2" value="{{ $faq->question }}" maxlength="255" required>
                        <textarea name="answer" class="form-control mb-2" rows="3" required>{{ $faq->answer }}</textarea>
                        <button class="btn btn-sm btn-primary">{{ __('admin.save') }}</button>
                        <button class="btn btn-sm btn-danger" form="faq-delete-{{ $faq->id }}"><i class="fas fa-trash"></i></button>
                    </form>
                    <form id="faq-delete-{{ $faq->id }}" action="{{ route('admin.content.faq.delete', $faq) }}" method="post" onsubmit="return confirm(I18N.content_delete_confirm)">@csrf @method('DELETE')</form>
                @endforeach
                <form action="{{ route('admin.content.faq.store') }}" method="post" class="border rounded p-3 bg-light">
                    @csrf
                    <x-form-input required type="text" name="question" title="{{ __('admin.content_question') }}"/>
                    <x-form-input required type="textarea" rows="3" name="answer" title="{{ __('admin.content_answer') }}"/>
                    <button class="btn btn-primary mt-3"><i class="fa fa-plus"></i> {{ __('admin.add') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">{{ __('admin.content_contacts') }}</div>
            <div class="card-body">
                <form action="{{ route('admin.content.contacts.save') }}" method="post" class="row">
                    @csrf
                    @foreach(['phone', 'email', 'address', 'website', 'working_hours'] as $field)
                        <div class="col-md-4"><x-form-input required type="text" name="{{ $field }}" title="{{ __('admin.content_'.$field) }}" value="{{ $contact?->$field }}"/></div>
                    @endforeach
                    <div class="col-12"><button class="btn btn-primary mt-3">{{ __('admin.save') }}</button></div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">{{ __('admin.content_services') }}</div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead><th>{{ __('admin.content_service_name') }}</th><th>{{ __('admin.content_service_branches') }}</th><th>{{ __('admin.actions') }}</th></thead>
                    <tbody>
                    @foreach($services as $service)
                        <tr>
                            <td>
                                <form action="{{ route('admin.content.services.save', $service) }}" method="post" class="d-flex gap-2">@csrf
                                    <input name="name" value="{{ $service->name }}" class="form-control form-control-sm" maxlength="64" required>
                                    <button class="btn btn-sm btn-primary">{{ __('admin.save') }}</button>
                                </form>
                            </td>
                            <td>{{ $service->branches_count }}</td>
                            <td>
                                <form action="{{ route('admin.content.services.delete', $service) }}" method="post" onsubmit="return confirm(I18N.content_delete_confirm)">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <form action="{{ route('admin.content.services.store') }}" method="post" class="d-flex gap-2">@csrf
                    <input name="name" class="form-control" maxlength="64" placeholder="{{ __('admin.content_service_name') }}" required>
                    <button class="btn btn-primary text-nowrap"><i class="fa fa-plus"></i> {{ __('admin.add') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
