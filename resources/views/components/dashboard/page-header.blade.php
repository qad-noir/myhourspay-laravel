@props(['eyebrow' => null, 'title', 'description' => null])
<div class="dashboard-page-header"><div>@if($eyebrow)<p>{{ $eyebrow }}</p>@endif<h1>{{ $title }}</h1>@if($description)<span>{{ $description }}</span>@endif</div><div>{{ $actions ?? '' }}</div></div>
