<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Spectral:wght@500;600;700&family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet" />

        <!-- Icônes -->
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/svg+xml" href="/images/app-icon.svg">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- PWA -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#A6472A">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script>
            // Enregistrement du Service Worker
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/service-worker.js')
                        .then(registration => {
                            console.log('ServiceWorker registered');
                        })
                        .catch(err => {
                            console.log('ServiceWorker registration failed: ', err);
                        });
                });
            }
        </script>
    </head>
    <body class="font-sans antialiased bg-paper text-ink">
        <div class="lg:flex lg:min-h-screen">
            @include('layouts.navigation')

            <div class="flex-1 flex flex-col min-h-screen min-w-0">
                <div class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 py-8 sm:py-10">
                    <!-- Page Heading -->
                    @isset($header)
                        <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
                            <div>{{ $header }}</div>
                            <div class="flex items-center gap-2">
                                <x-language-selector />
                                <button onclick="showShortcuts()" class="p-2 rounded-lg text-ink-soft hover:bg-paper2 hover:text-ink transition-colors" title="{{ __('messages.help_shortcut') }} (?)">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endisset

                    <!-- Page Content -->
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <x-footer />
            </div>
        </div>

        <x-keyboard-shortcuts />
        <x-enhanced-animations />
    </body>
</html>
