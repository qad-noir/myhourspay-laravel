@props([
    'title' => config('site.name'),
    'description' => config('site.description'),
    'canonical' => null,
    'index' => true,
])
@php
    $siteUrl = rtrim(config('site.url'), '/');
    $canonicalUrl = $canonical ?: url()->current();
    $imageUrl = $siteUrl.'/'.ltrim(config('site.social.image'), '/');
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $index ? 'index, follow' : 'noindex, nofollow' }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('site.name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $imageUrl }}">
<meta property="og:image:secure_url" content="{{ $imageUrl }}">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="{{ config('site.social.image_width') }}">
<meta property="og:image:height" content="{{ config('site.social.image_height') }}">
<meta property="og:image:alt" content="{{ config('site.social.image_alt') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $imageUrl }}">
<meta name="twitter:image:alt" content="{{ config('site.social.image_alt') }}">
