@extends('layouts.admin')
@section('title', 'Feature catalogue')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<p class="admin-page-description">A disabled feature overrides every plan and grant. When paid enforcement is off, all non-disabled capabilities remain available. Locked core features always stay free.</p>
@foreach($features as $category => $items)
<section class="admin-card feature-catalogue">
    <header><div><h2>{{ str($category)->replace('_',' ')->headline() }}</h2><p>{{ $items->count() }} {{ str('feature')->plural($items->count()) }}</p></div></header>
    <div class="feature-catalogue__list">
        @foreach($items as $feature)
        <form method="POST" action="{{ route('admin.billing.features.update', $feature) }}" data-confirm="Change this feature for every user and workspace?">
            @csrf @method('PUT')<input type="hidden" name="confirmed" value="1">
            <div><strong>{{ $feature->name }} @if($feature->locked)<span>Always free</span>@endif</strong><p>{{ $feature->description }}</p><small>{{ $feature->plans->pluck('name')->join(' · ') ?: 'No plan allocation' }}</small></div>
            <label>Mode<select name="mode" @disabled($feature->locked)>@foreach(['free','premium','disabled'] as $mode)<option value="{{ $mode }}" @selected($feature->mode===$mode)>{{ str($mode)->headline() }}</option>@endforeach</select>@if($feature->locked)<input type="hidden" name="mode" value="free">@endif</label>
            <label>Reason<input name="reason" required minlength="3" maxlength="500" placeholder="Audit reason"></label>
            <button>Save</button>
        </form>
        @endforeach
    </div>
</section>
@endforeach
@endsection
