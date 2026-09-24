<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? __('Carnet de présence numérique pour les groupes : une carte QR par membre, un scan par le responsable, des statistiques automatiques.') }}">
    <meta property="og:title" content="{{ $title ?? __('Système de vérification de présence') }}">
    <meta property="og:description" content="{{ $description ?? __('Carnet de présence numérique pour les groupes : une carte QR par membre, un scan par le responsable, des statistiques automatiques.') }}">
    <meta property="og:type" content="website">
    <title>{{ $title ?? __('Système de vérification de présence') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/images/app-icon.svg">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <x-fonts />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-ink antialiased bg-paper">

    <header class="border-b border-line bg-card">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 h-20 flex items-center justify-between">
            <a href="{{ route('welcome') }}" class="flex items-center gap-2.5">
                <x-application-logo class="w-8 h-8" />
                <span class="font-display font-semibold text-lg text-ink">Présence</span>
            </a>
            <nav class="flex items-center gap-3">
                <x-language-selector />
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-accent text-white font-bold text-sm hover:bg-accent-hover transition-colors">
                        {{ __('Tableau de bord') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-ink-soft hover:text-ink font-bold text-sm px-3 py-2 transition-colors">
                        {{ __('Connexion') }}
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-accent text-white font-bold text-sm hover:bg-accent-hover transition-colors">
                        {{ __('Créer un compte') }}
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    {{ $slot }}

    <x-footer />
</body>
</html>
