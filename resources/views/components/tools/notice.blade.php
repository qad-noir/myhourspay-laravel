@props(['icon' => 'pending', 'title', 'description', 'tone' => 'neutral'])

<div class="tool-notice tool-notice--{{ $tone }}">
    <span><x-dashboard.icon :name="$icon" :size="19" /></span>
    <div><strong>{{ $title }}</strong><p>{{ $description }}</p></div>
</div>
