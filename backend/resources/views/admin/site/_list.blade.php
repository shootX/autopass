{{-- One editable list: each row in ka/en/ru, a delete box per row and one empty row to add. --}}
<h6 class="mt-4">{{ __('admin.site_l_'.str_replace('.', '_', $name)) }}</h6>
<div class="table-responsive">
    <table class="table table-sm site-list">
        <thead>
            <tr>
                <th style="width:60px">#</th>
                @foreach(\App\Support\SitePages::LANGS as $lang)
                    <th class="site-lang">{{ $lang }}</th>
                @endforeach
                <th style="width:90px">{{ __('admin.site_delete') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_merge($list['items'], [[]]) as $i => $item)
                @php $isNew = $i === count($list['items']); @endphp
                <tr @if($isNew) class="table-light" @endif>
                    <td>{{ $isNew ? '+' : $i + 1 }}</td>
                    @foreach(\App\Support\SitePages::LANGS as $lang)
                        <td>
                            @foreach($list['fields'] as $field)
                                @if($field === 'a')
                                    <textarea class="form-control form-control-sm mb-1" rows="3" placeholder="{{ __('admin.site_f_'.$field) }}" name="lists[{{ $name }}][{{ $i }}][{{ $lang }}][{{ $field }}]">{{ $item[$lang][$field] ?? '' }}</textarea>
                                @else
                                    <input class="form-control form-control-sm mb-1" type="text" placeholder="{{ __('admin.site_f_'.$field) }}" name="lists[{{ $name }}][{{ $i }}][{{ $lang }}][{{ $field }}]" value="{{ $item[$lang][$field] ?? '' }}">
                                @endif
                            @endforeach
                        </td>
                    @endforeach
                    <td>
                        @unless($isNew)
                            <input class="form-check-input" type="checkbox" name="lists[{{ $name }}][{{ $i }}][delete]" value="1" aria-label="{{ __('admin.site_delete') }}">
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if($name === 'branches')
    <p class="text-muted small">{{ __('admin.site_branches_hint') }}</p>
@endif
