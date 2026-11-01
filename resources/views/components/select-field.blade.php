@props(['name', 'label', 'options', 'value' => null, 'required' => false, 'blank' => true, 'plain' => false])
<div @class(['mb-3' => ! $plain])>
    <label class="form-label" for="{{ $name }}">{{ $label }}@if($required) *@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($blank)<option value=""></option>@endif
        @foreach ($options as $option)
            <option value="{{ $option }}" @selected((string) old($name, $value) === (string) $option)>{{ $option }}</option>
        @endforeach
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
