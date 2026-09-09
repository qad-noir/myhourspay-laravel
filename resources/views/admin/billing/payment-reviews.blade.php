@extends('layouts.admin')
@section('title', 'Payment review')
@section('content')
<a class="admin-context-back" wire:navigate href="{{ route('admin.billing.health') }}">Back to Stripe health</a>
<section class="admin-card admin-table-card">
    <x-admin.data-table id="payment-reviews" :url="route('admin.billing.payment-reviews.data')" title="Refunds & disputes" description="Review payment changes in Stripe. Subscription access is managed separately; no automatic cancellation is applied."
        :columns="[['data'=>'stripe_object_id','name'=>'stripe_object_id','title'=>'Reference'],['data'=>'kind','name'=>'kind','title'=>'Type'],['data'=>'customer','title'=>'Customer','orderable'=>false,'searchable'=>false],['data'=>'total','title'=>'Amount','orderable'=>false,'searchable'=>false],['data'=>'status','name'=>'status','title'=>'Status'],['data'=>'updated_at','name'=>'updated_at','title'=>'Updated'],['data'=>'links','title'=>'Review','orderable'=>false,'searchable'=>false]]" />
</section>
@endsection
