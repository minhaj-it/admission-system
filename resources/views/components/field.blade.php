@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'plain' => false])
<div @class(['mb-3' => ! $plain])>
    <label class="form-label" for="{{ $name }}">{{ $label }}@if($required) *@endif</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        @if($required) aria-required="true" @endif
    >
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
