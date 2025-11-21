<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Système moderne de vérification et gestion de présence. Solution sécurisée, conforme RGPD avec statistiques avancées et QR codes.">
    <meta name="keywords" content="vérification présence, gestion présence, système présence, RGPD, sécurisé">
    <meta name="author" content="Système de Vérification de Présence">
    <meta property="og:title" content="Système de Vérification de Présence">
    <meta property="og:description" content="Solution moderne et sécurisée pour la gestion des présences">
    <meta property="og:type" content="website">
    <title>Système de Vérification de Présence - Solution moderne et sécurisée</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-4">
                <div class="flex flex-col sm:flex-row md:flex-row lg:flex-row justify-between items-center gap-4">
                    <h1 class="text-lg sm:text-xl md:text-2xl lg:text-2xl xl:text-3xl font-bold text-gray-900 text-center sm:text-left">Système de Vérification de Présence</h1>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-800 text-center px-3 py-2">Dashboard</a>
                        @else

                            <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-800 text-center px-3 py-2">Connexion</a>
                            <a href="{{ route('register') }}" class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-700 text-center transition-colors">S'inscrire</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-8 sm:py-12 md:py-16 lg:py-20">
            <div class="text-center">
                <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-extrabold text-gray-900 leading-tight">
                    Gestion de Présence
                    <span class="text-gray-700 block sm:inline">Simplifiée</span>
                </h2>
                <p class="mt-4 sm:mt-6 md:mt-8 text-base sm:text-lg md:text-xl lg:text-2xl text-gray-600 max-w-4xl mx-auto px-2 sm:px-4">
                    Un système moderne et efficace pour suivre et vérifier la présence.
                    Gérez facilement les présences avec notre interface intuitive.
                </p>

                <div class="mt-8 sm:mt-10 md:mt-12 flex flex-col sm:flex-row justify-center gap-4 sm:gap-6 px-2 sm:px-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-gray-800 text-white px-6 sm:px-8 md:px-10 py-3 md:py-4 rounded-lg text-base sm:text-lg md:text-xl font-medium hover:bg-gray-700 transition-all transform hover:scale-105">
                            Accéder au Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="bg-gray-800 text-white px-6 sm:px-8 md:px-10 py-3 md:py-4 rounded-lg text-base sm:text-lg md:text-xl font-medium hover:bg-gray-700 transition-all transform hover:scale-105">
                            Se connecter
                        </a>
                        <a href="{{ route('register') }}" class="border-2 border-gray-800 text-gray-800 px-6 sm:px-8 md:px-10 py-3 md:py-4 rounded-lg text-base sm:text-lg md:text-xl font-medium hover:bg-gray-100 transition-all transform hover:scale-105">
                            Créer un compte
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Features -->
            <div class="mt-12 sm:mt-16 md:mt-20 lg:mt-24 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8 md:gap-10 px-2 sm:px-4">
                <div class="bg-white p-4 sm:p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition-shadow text-center">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-4 md:mb-6">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900 mb-2 md:mb-4">Vérification Rapide</h3>
                    <p class="text-gray-600 text-sm sm:text-base md:text-lg leading-relaxed">Vérifiez la présence en quelques clics avec notre système optimisé.</p>
                </div>

                <div class="bg-white p-4 sm:p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition-shadow text-center">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-4 md:mb-6">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900 mb-2 md:mb-4">Statistiques</h3>
                    <p class="text-gray-600 text-sm sm:text-base md:text-lg leading-relaxed">Consultez les statistiques détaillées de présence et d'absence.</p>
                </div>

                <div class="bg-white p-4 sm:p-6 md:p-8 rounded-xl shadow-lg hover:shadow-xl transition-shadow text-center sm:col-span-2 md:col-span-1">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 md:w-16 md:h-16 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-4 md:mb-6">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl md:text-2xl font-semibold text-gray-900 mb-2 md:mb-4">Gestion Utilisateurs</h3>
                    <p class="text-gray-600 text-sm sm:text-base md:text-lg leading-relaxed">Interface simple pour gérer les utilisateurs et leurs présences.</p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <x-footer />
    </div>
</body>
</html>
