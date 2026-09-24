<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <x-fonts />

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
                    <div class="flex justify-end mb-2">
                        <x-language-selector />
                    </div>

                    <!-- Page Heading -->
                    @isset($header)
                        <div class="mb-8">{{ $header }}</div>
                    @endisset

                    <!-- Page Content -->
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <x-footer />
            </div>
        </div>

        <x-enhanced-animations />
    </body>
</html>
