@extends('layouts.admin') @section('title','Workspaces') @section('content')
<div class="admin-page-actions"><a wire:navigate class="admin-primary-action" href="{{ route('admin.workspaces.create') }}">＋ Create workspace</a></div>
<section class="admin-card admin-table-card"><x-admin.data-table id="workspaces-table" :url="route('admin.data.workspaces')" :columns="[['data'=>'name','title'=>'Workspace'],['data'=>'owner','title'=>'Owner'],['data'=>'members','title'=>'Members'],['data'=>'entries','title'=>'Entries'],['data'=>'target','title'=>'Target'],['data'=>'actions','title'=>'Actions','orderable'=>false,'searchable'=>false]]" /></section>
@endsection
