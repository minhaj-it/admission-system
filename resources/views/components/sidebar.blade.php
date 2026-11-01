@php
    $user = auth()->user();
    $allowed = fn (array $i) => empty($i['can']) || $user->can($i['can']);
    $isActive = fn (array $i) => ! empty($i['route']) && request()->routeIs($i['active'] ?? $i['route']);

    // Only entries the signed-in user may use are rendered (the server still re-checks every request).
    $items = [];
    foreach (config('menu.admin') as $item) {
        if (isset($item['title'])) {
            $items[] = $item;
            continue;
        }
        if (! empty($item['children'])) {
            $item['children'] = array_values(array_filter($item['children'], $allowed));
            if (! $item['children']) {
                continue;
            }
        } elseif (! $allowed($item)) {
            continue;
        }
        $items[] = $item;
    }
    // drop section titles that have nothing under them
    $items = array_values(array_filter($items, fn ($it, $k) => ! isset($it['title']) || (isset($items[$k + 1]) && ! isset($items[$k + 1]['title'])), ARRAY_FILTER_USE_BOTH));
@endphp
<aside class="sidebar" id="sidebar">
    <a href="{{ route('home') }}" class="brand"><x-brand /></a>
    <nav class="nav-group" aria-label="Main">
        @foreach ($items as $item)
            @if (isset($item['title']))
                <div class="nav-title">{{ $item['title'] }}</div>
            @elseif (empty($item['children']))
                <a href="{{ ! empty($item['route']) ? route($item['route']) : '#' }}"
                   @class(['side-link', 'active' => $isActive($item), 'is-pending' => empty($item['route'])])
                   @if (empty($item['route'])) aria-disabled="true" @endif>
                    <i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}
                </a>
            @else
                @php $open = collect($item['children'])->contains(fn ($c) => $isActive($c)); @endphp
                <a href="#m-{{ $item['id'] }}" data-bs-toggle="collapse"
                   @class(['side-link', 'active' => $open]) aria-expanded="{{ $open ? 'true' : 'false' }}">
                    <i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}<i class="bi bi-chevron-right chev"></i>
                </a>
                <div class="collapse {{ $open ? 'show' : '' }}" id="m-{{ $item['id'] }}">
                    @foreach ($item['children'] as $child)
                        <a href="{{ ! empty($child['route']) ? route($child['route']) : '#' }}"
                           @class(['sub-link', 'active' => $isActive($child), 'is-pending' => empty($child['route'])])
                           @if (empty($child['route'])) aria-disabled="true" @endif>{{ $child['label'] }}</a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>
</aside>
