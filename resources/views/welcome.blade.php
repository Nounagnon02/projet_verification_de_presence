<x-guest-layout>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Système de Vérification de Présence</h1>
                    <div class="space-x-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-indigo-600 hover:text-indigo-800">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-800">Connexion</a>
                            <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">S'inscrire</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h2 class="text-4xl font-extrabold text-gray-900 sm:text-5xl">
                    Gestion de Présence
                    <span class="text-indigo-600">Simplifiée</span>
                </h2>
                <p class="mt-6 text-xl text-gray-600 max-w-3xl mx-auto">
                    Un système moderne et efficace pour suivre et vérifier la présence. 
                    Gérez facilement les présences avec notre interface intuitive.
                </p>
                
                <div class="mt-10 flex justify-center space-x-6">
                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-indigo-600 text-white px-8 py-3 rounded-lg text-lg font-medium hover:bg-indigo-700 transition">
                            Accéder au Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="bg-indigo-600 text-white px-8 py-3 rounded-lg text-lg font-medium hover:bg-indigo-700 transition">
                            Se connecter
                        </a>
                        <a href="{{ route('register') }}" class="border border-indigo-600 text-indigo-600 px-8 py-3 rounded-lg text-lg font-medium hover:bg-indigo-50 transition">
                            Créer un compte
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Features -->
            <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Vérification Rapide</h3>
                    <p class="text-gray-600">Vérifiez la présence en quelques clics avec notre système optimisé.</p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Statistiques</h3>
                    <p class="text-gray-600">Consultez les statistiques détaillées de présence et d'absence.</p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Gestion Utilisateurs</h3>
                    <p class="text-gray-600">Interface simple pour gérer les utilisateurs et leurs présences.</p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="text-center text-gray-600">
                    <p>&copy; {{ date('Y') }} Système de Vérification de Présence. Tous droits réservés.</p>
                </div>
            </div>
        </footer>
    </div>
</x-guest-layout>