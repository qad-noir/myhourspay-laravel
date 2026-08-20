@props(['id', 'url', 'columns', 'title' => 'Records', 'description' => 'Search, sort and manage records'])
<div class="admin-datatable">
    <header class="admin-datatable__header">
        <div>
            <div class="admin-datatable__title-row">
                <h2>{{ $title }}</h2>
                <span data-table-count aria-live="polite">—</span>
            </div>
            <p>{{ $description }}</p>
        </div>
        <span class="admin-datatable__server-badge"><i></i>Live data</span>
    </header>
    <div class="admin-datatable__body">
        <table id="{{ $id }}" class="display responsive" data-admin-table data-url="{{ $url }}" data-columns='@json($columns)'>
            <thead><tr>@foreach($columns as $column)<th>{{ $column['title'] }}</th>@endforeach</tr></thead>
        </table>
    </div>
</div>
