@extends('admin.layout.app')

@push('css')
    <style>
        .site-key { font-size: 12px; color: #6c757d; font-family: monospace; }
        .site-lang { font-size: 11px; font-weight: 700; color: #6c757d; text-transform: uppercase; }
        .site-save { position: sticky; bottom: 0; z-index: 5; background: #fff; border-top: 1px solid #dee2e6; padding: 12px 0; }
        .site-list td { vertical-align: top; }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-4">
        @include('admin.site._tabs')

        <div class="d-flex justify-content-between align-items-center mt-3">
            <p class="text-muted mb-0">{{ __('admin.site_hint') }}</p>
            <form action="{{ route('admin.site.reset', $page) }}" method="post" onsubmit="return confirm(@js(__('admin.site_reset_confirm')))">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('admin.site_reset') }}</button>
            </form>
        </div>

        <form action="{{ route('admin.site.save', $page) }}" method="post">
            @csrf
            @foreach($groups as $group)
                @php
                    $groupTexts = array_filter($texts, fn ($t) => $t['group'] === $group);
                    $groupLists = array_filter($lists, fn ($l) => $l['group'] === $group);
                    $section = $sections[$group] ?? null;
                @endphp
                @continue(! $groupTexts && ! $groupLists && ! $section)
                <div class="card mt-4">
                    <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
                        <strong>{{ __('admin.site_g_'.$group) }}</strong>
                        @if($section)
                            <div class="d-flex gap-3 align-items-center">
                                <div class="form-check mb-0">
                                    <input type="hidden" name="sections[{{ $group }}][visible]" value="0">
                                    <input class="form-check-input" type="checkbox" id="vis-{{ $group }}" name="sections[{{ $group }}][visible]" value="1" @checked($section['visible'])>
                                    <label class="form-check-label" for="vis-{{ $group }}">{{ __('admin.site_visible') }}</label>
                                </div>
                                <label class="d-flex gap-2 align-items-center mb-0">{{ __('admin.site_order') }}
                                    <input class="form-control form-control-sm" style="width:70px" type="number" min="1" max="99" name="sections[{{ $group }}][order]" value="{{ $section['order'] }}">
                                </label>
                            </div>
                        @endif
                    </div>
                    <div class="card-body">
                        @foreach($groupTexts as $key => $text)
                            <div class="mb-3">
                                <div class="site-key">{{ $key }}</div>
                                <div class="row g-2">
                                    @foreach(\App\Support\SitePages::LANGS as $lang)
                                        <div class="col-md-4">
                                            <span class="site-lang">{{ $lang }}</span>
                                            @if(mb_strlen($defaults['texts'][$key][$lang]) > 70)
                                                <textarea class="form-control" rows="3" name="texts[{{ $key }}][{{ $lang }}]">{{ $text[$lang] }}</textarea>
                                            @else
                                                <input class="form-control" type="text" name="texts[{{ $key }}][{{ $lang }}]" value="{{ $text[$lang] }}">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @foreach($groupLists as $name => $list)
                            @include('admin.site._list', ['name' => $name, 'list' => $list])
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="site-save mt-4">
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
@endsection
