@props(['id', 'url', 'columns', 'title' => 'Records', 'description' => 'Search, sort and manage records', 'order' => [[0, 'asc']]])
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
        <div class="admin-alert admin-alert--error" data-table-error role="alert" hidden><span data-table-error-message></span> <button type="button" class="admin-button" data-table-error-retry>Retry</button></div>
        <table id="{{ $id }}" class="display responsive" data-admin-table data-url="{{ $url }}" data-columns='@json($columns)' data-order='@json($order)'>
            <thead><tr>@foreach($columns as $column)<th>{{ $column['title'] }}</th>@endforeach</tr></thead>
        </table>
    </div>
</div>
