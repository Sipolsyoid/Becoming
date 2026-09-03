<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#FAF8F5] text-[#2B3E51]">
        <div class="relative min-h-screen overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-br from-[#76C7B7]/25 via-transparent to-[#5DA068]/20 opacity-70 motion-safe:animate-[becoming-bg-pan_22s_ease-in-out_infinite_alternate]"></div>

                <div class="absolute -top-28 -left-28 h-80 w-80 rounded-full bg-[#76C7B7]/30 blur-3xl motion-safe:animate-[becoming-blob-1_20s_ease-in-out_infinite]"></div>
                <div class="absolute top-1/3 -right-28 h-80 w-80 rounded-full bg-[#5DA068]/20 blur-3xl motion-safe:animate-[becoming-blob-2_24s_ease-in-out_infinite]"></div>
                <div class="absolute -bottom-28 left-1/3 h-96 w-96 rounded-full bg-[#377991]/20 blur-3xl motion-safe:animate-[becoming-blob-3_22s_ease-in-out_infinite]"></div>
            </div>

            <div class="relative min-h-screen">
                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white/70 backdrop-blur border-b border-[#2B3E51]/10 shadow-sm">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="motion-safe:animate-[becoming-fade-in_500ms_ease-out_both]">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
