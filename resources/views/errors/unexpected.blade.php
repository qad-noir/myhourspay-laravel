<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><x-site-meta title="Something went wrong · myhourspay" :index="false" />
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=dm-sans:400,600,700&family=manrope:700,800&display=swap" rel="stylesheet">@vite(['resources/css/app.css'])
</head>
<body class="auth-shell__form">
<main class="auth-panel error-panel"><x-brand-logo /><p class="auth-eyebrow">Request interrupted</p><h2>Something went wrong</h2><p class="auth-panel__intro">We couldn’t complete this request. The error has been logged and administrators have been notified.</p><div class="auth-status"><strong>Support reference</strong><br>{{ $reference }}</div><a class="public-button public-button--primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Return safely</a></main>
</body>
</html>
