@php $logo = \App\Models\Institution::current()->logoUrl(); @endphp
<div {{ $attributes->class(['inst-logo']) }}>
    @if ($logo)
        <img src="{{ $logo }}" alt="Institute logo" class="logo-img">
    @else
        <i class="bi bi-mortarboard-fill"></i>
    @endif
</div>
