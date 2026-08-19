@extends('layouts.admin') @section('title','Users') @section('content')
<div class="admin-page-actions"><a class="admin-primary-action" href="{{ route('admin.users.create') }}">＋ Create user</a></div>
<section class="admin-card admin-table-card"><x-admin.data-table id="users-table" :url="route('admin.data.users')" :columns="[['data'=>'name','title'=>'User'],['data'=>'status','title'=>'Status'],['data'=>'workspaces','title'=>'Workspaces'],['data'=>'entries','title'=>'Entries'],['data'=>'joined','title'=>'Joined'],['data'=>'actions','title'=>'Actions','orderable'=>false,'searchable'=>false]]" /></section>
@endsection
