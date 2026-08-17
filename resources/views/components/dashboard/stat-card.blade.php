@props(['label', 'value', 'support', 'tone' => 'neutral', 'icon' => 'clock'])
<article class="dashboard-stat dashboard-stat--{{ $tone }}"><div><span>{{ $label }}</span><i><x-dashboard.icon :name="$icon" /></i></div><strong>{{ $value }}</strong><p>{{ $support }}</p></article>
