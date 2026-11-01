@props(['name', 'label', 'value' => null, 'required' => false])
<div>
    <label class="form-label" for="{{ $name }}">{{ $label }}@if($required) *@endif</label>
    <div class="input-group has-validation">
        <span class="input-group-text">+88</span>
        <input id="{{ $name }}" name="{{ $name }}" type="tel" inputmode="numeric" value="{{ old($name, $value) }}" placeholder="01XXXXXXXXX"
               {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
