<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased bg-gray-50">
    <div class="min-h-screen flex flex-col lg:flex-row">
        <!-- Section gauche - Branding -->
        <div class="lg:w-1/2 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center p-6 sm:p-8 md:p-12">
            <div class="text-center text-white max-w-md">
                <div class="w-20 h-20 sm:w-24 sm:h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4">Système de Présence</h1>
                <p class="text-gray-300 text-sm sm:text-base md:text-lg leading-relaxed">
                    Gérez efficacement la présence de vos membres avec notre solution moderne et intuitive.
                </p>
                <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs sm:text-sm">
                    <div class="text-center">
                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <p>Rapide</p>
                    </div>
                    <div class="text-center">
                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <p>Sécurisé</p>
                    </div>
                    <div class="text-center">
                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <p>Analytique</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section droite - Formulaire -->
        <div class="lg:w-1/2 flex items-center justify-center p-6 sm:p-8 md:p-12">
            <div class="w-full max-w-md">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 sm:p-8 md:p-10">
                    {{ $slot }}
                </div>
                
                <!-- Lien retour accueil -->
                <div class="text-center mt-6">
                    <a href="{{ route('welcome') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>