<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Icônes -->
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/svg+xml" href="/images/app-icon.svg">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Fonts -->
        <x-fonts />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased bg-paper">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 sm:px-6 py-10 sm:py-14">
            <div class="w-full max-w-[440px] bg-card border border-line rounded-xl shadow-[0_1px_2px_rgba(42,36,31,0.05),0_12px_32px_rgba(42,36,31,0.08)] px-7 sm:px-11 py-9 sm:py-12">
                <!-- Marque -->
                <div class="flex items-center justify-center gap-2 mb-7">
                    <x-application-logo class="w-7 h-7" />
                    <span class="text-xs font-bold uppercase tracking-widest text-ink-soft">Présence</span>
                </div>

                {{ $slot }}
            </div>

            <!-- Lien retour -->
            <div class="mt-7 text-center">
                <a href="{{ route('welcome') }}" class="inline-flex items-center text-sm font-semibold text-ink-soft hover:text-ink transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Retour à l'accueil
                </a>
            </div>
        </div>

        <!-- Footer -->
        <x-footer />
    </body>
</html>
