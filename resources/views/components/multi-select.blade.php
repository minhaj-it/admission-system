@props(['name', 'options', 'selected' => [], 'all' => false])
@php $selected = array_map('strval', (array) $selected); @endphp
<div class="dropdown multi" data-key="{{ $name }}">
    <button type="button" class="form-select multi-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Select</button>
    <div class="dropdown-menu multi-menu">
        @if ($all)
            <label class="fw-semibold"><input type="checkbox" class="form-check-input m-0" data-all> Select all</label>
        @endif
        @foreach ($options as $id => $label)
            <label><input type="checkbox" class="form-check-input m-0" name="{{ $name }}[]" value="{{ $id }}" data-label="{{ $label }}" @checked(in_array((string) $id, $selected, true))> {{ $label }}</label>
        @endforeach
    </div>
</div>
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
