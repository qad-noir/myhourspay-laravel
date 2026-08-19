@props(['id','url','columns'])
<div class="admin-datatable"><table id="{{ $id }}" class="display responsive nowrap" data-admin-table data-url="{{ $url }}" data-columns='@json($columns)'><thead><tr>@foreach($columns as $column)<th>{{ $column['title'] }}</th>@endforeach</tr></thead></table></div>
