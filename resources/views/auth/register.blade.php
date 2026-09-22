<x-guest-layout>
    <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-[26px] text-ink mb-2">Créer un compte</h1>
        <p class="text-ink-soft text-base leading-relaxed">Rejoignez le système de gestion de présence.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Name -->
        <div class="flex flex-col gap-2">
            <x-input-label for="name" :value="__('Nom complet')" />
            <x-text-input id="name"
                type="text"
                name="name"
                :value="old('name')"
                placeholder="Votre nom complet"
                required
                autofocus
                autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <!-- Group -->
        <div class="flex flex-col gap-2">
            <x-input-label for="group_name" :value="__('Nom du groupe')" />
            <x-text-input id="group_name"
                type="text"
                name="group_name"
                :value="old('group_name')"
                placeholder="Ex: Groupe de prière, Chorale..."
                required />
            <x-input-error :messages="$errors->get('group_name')" />
            <p class="text-sm text-ink-faint">Le nom de votre groupe ou organisation. Vous pourrez ajouter d'autres co-responsables ensuite.</p>
        </div>

        <!-- Email Address -->
        <div class="flex flex-col gap-2">
            <x-input-label for="email" :value="__('Adresse email')" />
            <x-text-input id="email"
                type="email"
                name="email"
                :value="old('email')"
                placeholder="vous@exemple.com"
                required
                autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div class="flex flex-col gap-2">
            <x-input-label for="password" :value="__('Mot de passe')" />
            <x-text-input id="password"
                type="password"
                name="password"
                placeholder="Choisissez un mot de passe sécurisé"
                required
                autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Confirm Password -->
        <div class="flex flex-col gap-2">
            <x-input-label for="password_confirmation" :value="__('Confirmer le mot de passe')" />
            <x-text-input id="password_confirmation"
                type="password"
                name="password_confirmation"
                placeholder="Confirmez votre mot de passe"
                required
                autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="space-y-4 pt-2">
            <x-primary-button class="w-full">
                {{ __("Créer mon compte") }}
            </x-primary-button>

            <p class="text-center text-[15px] text-ink-soft">
                {{ __('Déjà un compte ?') }}
                <a class="font-bold" href="{{ route('login') }}">
                    {{ __('Se connecter') }}
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>
