@props(['label', 'value', 'support', 'tone' => 'neutral', 'icon' => '◷'])
<article class="dashboard-stat dashboard-stat--{{ $tone }}"><div><span>{{ $label }}</span><i aria-hidden="true">{{ $icon }}</i></div><strong>{{ $value }}</strong><p>{{ $support }}</p></article>
