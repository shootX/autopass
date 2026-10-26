@extends('admin.layout.app')

@section('content')
    <div class="container-fluid px-4">
        @include('admin.site._tabs')

        <div class="card mt-4 mb-4">
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <th>{{ __('admin.date') }}</th>
                        <th>{{ __('admin.site_company') }}</th>
                        <th>{{ __('admin.phone') }}</th>
                        <th>{{ __('admin.site_lang') }}</th>
                        <th>{{ __('admin.status') }}</th>
                        <th>{{ __('admin.actions') }}</th>
                    </thead>
                    <tbody>
                    @forelse($leads as $lead)
                        <tr @if($lead->handled_at) class="text-muted" @endif>
                            <td>{{ $lead->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $lead->company }}</td>
                            <td><a href="tel:{{ preg_replace('/[^\d+]/', '', $lead->phone) }}">{{ $lead->phone }}</a></td>
                            <td>{{ strtoupper((string) $lead->lang) }}</td>
                            <td>{{ $lead->handled_at ? __('admin.site_lead_handled') : __('admin.site_lead_new') }}</td>
                            <td>
                                <div class="btn-group">
                                    <form action="{{ route('admin.site.leads.handle', $lead) }}" method="post">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary" type="submit">{{ $lead->handled_at ? __('admin.site_lead_reopen') : __('admin.site_lead_done') }}</button>
                                    </form>
                                    <form action="{{ route('admin.site.leads.delete', $lead) }}" method="post" onsubmit="return confirm(@js(__('admin.site_lead_delete_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger ms-1" type="submit"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">{{ __('admin.site_leads_empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $leads->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
