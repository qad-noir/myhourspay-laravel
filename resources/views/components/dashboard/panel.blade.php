@props(['title' => null, 'description' => null])
<section {{ $attributes->class('dashboard-panel') }}>@if($title)<header><div><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>{{ $actions ?? '' }}</header>@endif<div class="dashboard-panel__body">{{ $slot }}</div></section>
