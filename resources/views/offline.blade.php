<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Hors ligne') }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-paper2 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-card shadow-lg rounded-lg p-8 text-center">
        <div class="mb-6">
            <svg class="w-20 h-20 mx-auto text-ink-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l4 4m0-4l-4 4"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-ink mb-2">{{ __('Vous êtes hors ligne') }}</h1>
        <p class="text-ink-soft mb-6">
            {{ __("Il semble que vous n'ayez pas de connexion internet. Vérifiez votre connexion et réessayez.") }}
        </p>
        <button onclick="window.location.reload()" class="bg-accent hover:bg-accent-hover text-white font-bold py-2 px-4 rounded transition-colors">
            {{ __('Réessayer') }}
        </button>
    </div>
</body>
</html>
