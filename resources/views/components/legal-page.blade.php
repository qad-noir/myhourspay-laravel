@props(['title', 'description'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}"><x-site-meta :title="$title.' · myhourspay'" :index="false" />
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body legal-body">
<x-public-navbar />
<main class="public-container legal-main" id="main-content">
    <header class="legal-heading"><p class="public-eyebrow">Your records. Your trust.</p><h1>{{ $title }}</h1><p>{{ $description }}</p></header>
    <div class="legal-grid">
        <aside class="legal-sidebar"><nav aria-label="Legal information"><a href="{{ url('/policy') }}" @if($title === 'Privacy policy') aria-current="page" @endif>Privacy policy</a><a href="{{ url('/terms') }}" @if($title === 'Terms of service') aria-current="page" @endif>Terms of service</a></nav><p>Questions about your account or your information?</p><a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a></aside>
        <article class="legal-document">{{ $slot }}</article>
    </div>
</main>
<x-public-footer />
</body>
</html>
