<x-guest-layout>
    <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-[26px] text-ink mb-2">{{ __('Ravis de vous revoir') }}</h1>
        <p class="text-ink-soft text-base leading-relaxed">{{ __('Connectez-vous pour gérer la présence de vos groupes.') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div class="flex flex-col gap-2">
            <x-input-label for="email" :value="__('Adresse email')" />
            <x-text-input id="email"
                type="email"
                name="email"
                :value="old('email')"
                :placeholder="__('vous@exemple.com')"
                required
                autofocus
                autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div class="flex flex-col gap-2">
            <x-input-label for="password" :value="__('Mot de passe')" />
            <x-text-input id="password"
                type="password"
                name="password"
                placeholder="••••••••"
                required
                autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between flex-wrap gap-2">
            <label for="remember_me" class="inline-flex items-center gap-2.5 text-[15px] text-ink">
                <input id="remember_me" type="checkbox" class="w-5 h-5 rounded border-line-strong text-accent focus:ring-accent-focus" name="remember">
                {{ __('Se souvenir de moi') }}
            </label>

            @if (Route::has('password.request'))
                <a class="text-[15px] font-bold" href="{{ route('password.request') }}">
                    {{ __('Mot de passe oublié ?') }}
                </a>
            @endif
        </div>

        <div class="space-y-4 pt-2">
            <x-primary-button class="w-full">
                {{ __('Se connecter') }}
            </x-primary-button>

            <p class="text-center text-[15px] text-ink-soft">
                {{ __('Pas encore de compte ?') }}
                <a class="font-bold" href="{{ route('register') }}">
                    {{ __('Créer un compte') }}
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>
