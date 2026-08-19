@extends('layouts.admin') @section('title','Operational incidents') @section('content')
<section class="admin-card admin-table-card"><x-admin.data-table id="incidents-table" :url="route('admin.data.incidents')" :columns="[['data'=>'reference','title'=>'Reference'],['data'=>'event','title'=>'Event'],['data'=>'severity','title'=>'Severity'],['data'=>'email','title'=>'Email'],['data'=>'status','title'=>'Status'],['data'=>'date','title'=>'Occurred'],['data'=>'details','title'=>'Details','orderable'=>false,'searchable'=>false]]" /></section>
@endsection
