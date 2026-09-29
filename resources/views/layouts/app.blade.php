<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'Becoming') }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="becoming-app font-sans antialiased">
<a href="#main-content" class="skip-link">Skip to content</a>
@include('layouts.navigation')
@isset($header)<header class="page-heading">
<div class="page-heading-inner">
<div>
<p class="eyebrow">YOUR EVERYDAY, A LITTLE BETTER</p>{{ $header }}</div>
<span class="date-chip">{{ now()->format('l, j F') }} · UTC</span>
</div>
</header>@endisset
<main id="main-content" tabindex="-1">@if(session('status'))<div class="feedback-message" role="status">{{ session('status') }}</div>@endif{{ $slot }}</main>
<footer class="app-footer">
<span>Becoming</span>
<span>Small steps. Lasting change.</span>
</footer>
</body>
</html>
