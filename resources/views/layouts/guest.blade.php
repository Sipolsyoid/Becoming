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
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 motion-safe:opacity-0 motion-safe:animate-[becoming-fade-in_1800ms_ease-out_both]">
                <div class="absolute inset-0 bg-gradient-to-br from-[#76C7B7]/25 via-transparent to-[#5DA068]/20 opacity-80 motion-safe:animate-[becoming-bg-pan_18s_ease-in-out_infinite_alternate]"></div>

                <div class="absolute -top-28 -left-28 h-80 w-80 rounded-full bg-[#76C7B7]/40 blur-3xl motion-safe:animate-[becoming-blob-1_18s_ease-in-out_infinite]"></div>
                <div class="absolute top-1/3 -right-28 h-80 w-80 rounded-full bg-[#5DA068]/25 blur-3xl motion-safe:animate-[becoming-blob-2_22s_ease-in-out_infinite]"></div>
                <div class="absolute -bottom-28 left-1/3 h-96 w-96 rounded-full bg-[#377991]/25 blur-3xl motion-safe:animate-[becoming-blob-3_20s_ease-in-out_infinite]"></div>

                <div class="absolute top-24 left-1/2 h-2 w-2 rounded-full bg-[#76C7B7]/70 motion-safe:animate-[becoming-blob-1_14s_ease-in-out_infinite]"></div>
                <div class="absolute bottom-24 left-24 h-2 w-2 rounded-full bg-[#76C7B7]/70 motion-safe:animate-[becoming-blob-2_16s_ease-in-out_infinite]"></div>
                <div class="absolute top-1/2 right-24 h-2.5 w-2.5 rounded-full bg-[#76C7B7]/70 motion-safe:animate-[becoming-blob-3_15s_ease-in-out_infinite]"></div>
            </div>

            <div class="relative flex min-h-screen items-center justify-center px-4 py-10">
                <div class="w-full max-w-6xl overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:opacity-0 motion-safe:animate-[becoming-fade-in_1800ms_ease-out_both]">
                    <div class="grid grid-cols-1 lg:grid-cols-2">
                        <div class="hidden lg:flex flex-col p-10 bg-gradient-to-br from-[#5DA068]/20 via-white/0 to-[#377991]/20">
                            <div class="flex flex-1 flex-col items-center justify-center text-center">
                                <a href="/" class="flex w-full items-center justify-center">
                                    <img src="{{ asset('img/big-logo2.png') }}" alt="Becoming" class="w-[600px] max-w-full h-auto select-none drop-shadow-sm" />
                                </a>
                                <p class="mt-2 max-w-sm text-sm leading-6 text-[#2B3E51]/75">Small steps, every day. Sign in and keep moving.</p>
                            </div>

                            <div class="flex items-center justify-center gap-2 text-xs text-[#2B3E51]/60">
                                <span class="inline-flex h-2 w-2 rounded-full bg-[#76C7B7]"></span>
                                <span class="inline-flex h-2 w-2 rounded-full bg-[#377991]"></span>
                                <span class="inline-flex h-2 w-2 rounded-full bg-[#5DA068]"></span>
                                <span class="ms-2">Secure authentication</span>
                            </div>
                        </div>

                        <div class="p-6 sm:p-10">
                            <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                                <a href="/" class="flex w-full items-center justify-center">
                                    <img src="{{ asset('img/big-logo2.png') }}" alt="Becoming" class="w-[460px] max-w-full h-auto select-none drop-shadow-sm" />
                                </a>
                                <p class="mt-3 text-sm text-[#2B3E51]/75">Small steps, every day. Sign in and keep moving.</p>
                            </div>

                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
