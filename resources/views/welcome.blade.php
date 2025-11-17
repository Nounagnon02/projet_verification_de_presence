<x-guest-layout>
    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 text-center sm:text-left">Système de Vérification de Présence</h1>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-800 text-center">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-800 text-center">Connexion</a>
                            <a href="{{ route('register') }}" class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-700 text-center">S'inscrire</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-16">
            <div class="text-center">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900">
                    Gestion de Présence
                    <span class="text-gray-700">Simplifiée</span>
                </h2>
                <p class="mt-4 sm:mt-6 text-lg sm:text-xl text-gray-600 max-w-3xl mx-auto px-4">
                    Un système moderne et efficace pour suivre et vérifier la présence. 
                    Gérez facilement les présences avec notre interface intuitive.
                </p>
                
                <div class="mt-8 sm:mt-10 flex flex-col sm:flex-row justify-center gap-4 sm:gap-6 px-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-gray-800 text-white px-6 sm:px-8 py-3 rounded-lg text-base sm:text-lg font-medium hover:bg-gray-700 transition">
                            Accéder au Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="bg-gray-800 text-white px-6 sm:px-8 py-3 rounded-lg text-base sm:text-lg font-medium hover:bg-gray-700 transition">
                            Se connecter
                        </a>
                        <a href="{{ route('register') }}" class="border border-gray-800 text-gray-800 px-6 sm:px-8 py-3 rounded-lg text-base sm:text-lg font-medium hover:bg-gray-100 transition">
                            Créer un compte
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Features -->
            <div class="mt-12 sm:mt-20 grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8 px-4">
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Vérification Rapide</h3>
                    <p class="text-gray-600 text-sm sm:text-base">Vérifiez la présence en quelques clics avec notre système optimisé.</p>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Statistiques</h3>
                    <p class="text-gray-600 text-sm sm:text-base">Consultez les statistiques détaillées de présence et d'absence.</p>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Gestion Utilisateurs</h3>
                    <p class="text-gray-600 text-sm sm:text-base">Interface simple pour gérer les utilisateurs et leurs présences.</p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t mt-12 sm:mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
                <div class="text-center text-gray-600">
                    <p class="text-sm sm:text-base">&copy; {{ date('Y') }} Système de Vérification de Présence. Tous droits réservés.</p>
                </div>
            </div>
        </footer>
    </div>
</x-guest-layout>