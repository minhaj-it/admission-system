@php
    $words = explode(' ', trim(config('admission.brand')));
    $last = array_pop($words);
@endphp
<i class="bi bi-mortarboard-fill"></i><span>{{ implode(' ', $words) }} <b>{{ $last }}</b></span>
