@props([
    'name', 'placeholder' => null, 'type', 'title', 'value' => null, 'small' => null, 'rows' => null, 'required' => false
])

<div class="form-group mt-3">
    <label for="{{$name}}_field_id">{{$title}}@if($required)<span class="text-danger">*</span>@endif</label>
    @if($type == "textarea")
        <textarea @if($required) required @endif name="{{$name}}" @if($rows) rows="{{$rows}}" @endif class="form-control @error($name) is-invalid @enderror" id="{{$name}}_field_id" placeholder="{{$placeholder ?? $title}}">{{old($name) ?? $value}}</textarea>
    @else
        <input @if($required) required @endif name="{{$name}}" type="{{$type}}" value="{{old($name) ?? $value}}" class="form-control @error($name) is-invalid @enderror" id="{{$name}}_field_id" placeholder="{{$placeholder ?? $title}}">
    @endif
    @if($small)
        <small>{{$small}}</small>
    @endif
    @error($name)
        <small style="color: red;">{{ $message }}</small>
    @enderror
</div>
