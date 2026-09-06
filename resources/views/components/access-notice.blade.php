@props(['workspace' => null])
@php($notice = auth()->check() ? app(App\Services\AccessNotice::class)->for(auth()->user(), request()->routeIs('billing.*') ? null : $workspace) : null)
@if($notice)
<aside class="access-notice" aria-label="Your access period">
    <div><strong>{{ $notice['title'] }}</strong><span>{{ $notice['detail'] }}</span></div>
    @if($notice['action'])<a wire:navigate href="{{ route('billing.index') }}">{{ $notice['action'] }}</a>@endif
</aside>
@endif
