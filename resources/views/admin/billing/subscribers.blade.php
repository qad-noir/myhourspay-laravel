@extends('layouts.admin')
@section('title', 'Subscribers')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.overview') }}"><x-admin.icon name="back"/>Back to monetisation</a>
<section class="admin-card admin-table-card"><x-admin.data-table id="subscribers-table" :url="route('admin.data.billing.subscribers')" title="Stripe subscribers" description="Local status updated from signed webhooks" :columns="[['data'=>'subscriber','name'=>'subscriber','title'=>'Customer','responsivePriority'=>1],['data'=>'plan','name'=>'plan','title'=>'Plan'],['data'=>'status','name'=>'status','title'=>'Status','responsivePriority'=>2],['data'=>'renewal','name'=>'renewal','title'=>'Renewal'],['data'=>'actions','title'=>'','orderable'=>false,'searchable'=>false,'responsivePriority'=>1]]" /></section>
@endsection
