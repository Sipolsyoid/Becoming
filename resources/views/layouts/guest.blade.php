<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'Becoming') }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="becoming-app font-sans antialiased">
<main class="auth-shell">
<section class="auth-story">
<a href="/">
<img src="{{ asset('img/big-logo2.png') }}" alt="Becoming" class="w-44">
</a>
<div class="my-12 lg:my-24">
<p class="eyebrow">MAKE ROOM FOR WHAT MATTERS</p>
<h1>A little today.<br>
<em>A different tomorrow.</em>
</h1>
<p class="mt-6 max-w-sm leading-7">Build your daily rhythm, capture the small wins, and see how far you’ve come.</p>
</div>
<div class="auth-steps">
<span>01 &nbsp; Choose a habit</span>
<span>02 &nbsp; Capture your progress</span>
<span>03 &nbsp; Keep becoming</span>
</div>
</section>
<section class="auth-form">{{ $slot }}</section>
</main>
</body>
</html>
