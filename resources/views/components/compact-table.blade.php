@props(['id', 'url', 'columns', 'tableClass' => 'admin-table'])
<div class="compact-table admin-datatable">
    <div class="compact-table-error" role="status" hidden>Records could not be loaded.<button type="button" data-table-retry>Retry</button></div>
    <table id="{{ $id }}" class="{{ $tableClass }}" data-compact-table data-url="{{ $url }}" data-columns='@json($columns)'>
        <thead><tr>@foreach($columns as $column)<th scope="col">{{ $column['title'] }}</th>@endforeach</tr></thead>
    </table>
</div>
