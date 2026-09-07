<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}"><x-site-meta title="FAQ · myhourspay" description="Answers about myhourspay hours, reports, plans and workspace approvals." />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=manrope:500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body public-page-body">
<x-public-navbar />
<main>
    <x-faq-section
        :items="$items"
        :standalone="true"
        title="Answers for clearer working hours."
        description="Everything you need to know about tracking time, preparing reports and choosing the right plan for your work."
        support-title="Still need a hand?"
        support-copy="Tell us what you are trying to do and we will point you in the right direction."
    />
</main>
<x-public-footer />
</body>
</html>
