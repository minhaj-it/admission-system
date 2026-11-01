@if (session('status') || session('error'))
    <div id="flash" hidden
         data-type="{{ session('error') ? 'danger' : 'success' }}"
         data-message="{{ session('error') ?? session('status') }}"></div>
@endif
