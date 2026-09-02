@props(['title' => 'Narrow this view', 'description' => 'Filters update the records below.'])

<div {{ $attributes->class(['admin-filter']) }}>
    <div class="admin-filter__intro">
        <span><x-admin.icon name="filter" /></span>
        <div><strong>{{ $title }}</strong><small>{{ $description }}</small></div>
    </div>
    <div class="admin-filter__controls">{{ $slot }}</div>
</div>
