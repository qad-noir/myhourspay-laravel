@extends('layouts.admin')
@section('title', 'Premium capability usage')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<section class="admin-card">
    <header><div><h2>All premium capabilities</h2><p>Feature middleware usage over 30 days, highest first. Includes capabilities with no recorded usage.</p></div></header>
    <div class="admin-audit">
        @forelse($features as $feature)
            <div><strong>{{ $feature->name }} <small>· {{ str($feature->category)->headline() }}</small></strong><span>{{ number_format($feature->uses) }} uses</span></div>
        @empty
            <p>No capabilities are available yet.</p>
        @endforelse
    </div>
</section>
{{ $features->links() }}
@endsection
