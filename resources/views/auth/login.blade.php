<x-guest-layout>
    <div class="text-center mb-6 sm:mb-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Connexion</h2>
        <p class="text-gray-600 text-sm sm:text-base">Accédez à votre espace de gestion</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

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
                autofocus 
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
                placeholder="Votre mot de passe"
                required 
                autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-gray-600 shadow-sm focus:ring-gray-500" name="remember">
                <span class="ml-2 text-sm text-gray-600">{{ __('Se souvenir de moi') }}</span>
            </label>
            
            @if (Route::has('password.request'))
                <a class="text-sm text-gray-600 hover:text-gray-900 transition-colors" href="{{ route('password.request') }}">
                    {{ __('Mot de passe oublié?') }}
                </a>
            @endif
        </div>

        <div class="space-y-4">
            <x-primary-button class="w-full py-3 px-4 bg-gray-800 hover:bg-gray-700 focus:bg-gray-700 rounded-lg font-semibold text-base transition-all transform hover:scale-105">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                {{ __('Se connecter') }}
            </x-primary-button>
            
            <div class="text-center">
                <span class="text-sm text-gray-600">Pas encore de compte? </span>
                <a class="text-sm text-gray-800 hover:text-gray-600 font-semibold transition-colors" href="{{ route('register') }}">
                    {{ __('Créer un compte') }}
                </a>
            </div>
        </div>
    </form>
</x-guest-layout>
