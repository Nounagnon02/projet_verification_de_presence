<x-guest-layout>
    <div class="text-center mb-6 sm:mb-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Créer un compte</h2>
        <p class="text-gray-600 text-sm sm:text-base">Rejoignez le système de gestion de présence</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Nom complet')" class="text-sm font-semibold text-gray-700 mb-2" />
            <x-text-input id="name" 
                class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all text-sm sm:text-base" 
                type="text" 
                name="name" 
                :value="old('name')" 
                placeholder="Votre nom complet"
                required 
                autofocus 
                autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Group -->
        <div>
            <x-input-label for="group" :value="__('Nom du groupe')" class="text-sm font-semibold text-gray-700 mb-2" />
            <x-text-input id="group" 
                class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all text-sm sm:text-base" 
                type="text" 
                name="group" 
                :value="old('group')" 
                placeholder="Ex: Groupe de prière, Chorale..."
                required />
            <x-input-error :messages="$errors->get('group')" class="mt-2" />
            <p class="text-xs sm:text-sm text-gray-500 mt-1">Le nom de votre groupe ou organisation</p>
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Adresse email')" class="text-sm font-semibold text-gray-700 mb-2" />
            <x-text-input id="email" 
                class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all text-sm sm:text-base" 
                type="email" 
                name="email" 
                :value="old('email')" 
                placeholder="votre@email.com"
                required 
                autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Mot de passe')" class="text-sm font-semibold text-gray-700 mb-2" />
            <x-text-input id="password" 
                class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all text-sm sm:text-base" 
                type="password" 
                name="password" 
                placeholder="Choisissez un mot de passe sécurisé"
                required 
                autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirmer le mot de passe')" class="text-sm font-semibold text-gray-700 mb-2" />
            <x-text-input id="password_confirmation" 
                class="block w-full py-3 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all text-sm sm:text-base" 
                type="password" 
                name="password_confirmation" 
                placeholder="Confirmez votre mot de passe"
                required 
                autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="space-y-4">
            <x-primary-button class="w-full py-3 px-4 bg-gray-800 hover:bg-gray-700 focus:bg-gray-700 rounded-lg font-semibold text-base transition-all transform hover:scale-105">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                {{ __("Créer mon compte") }}
            </x-primary-button>
            
            <div class="text-center">
                <span class="text-sm text-gray-600">Déjà un compte? </span>
                <a class="text-sm text-gray-800 hover:text-gray-600 font-semibold transition-colors" href="{{ route('login') }}">
                    {{ __('Se connecter') }}
                </a>
            </div>
        </div>
    </form>
</x-guest-layout>
