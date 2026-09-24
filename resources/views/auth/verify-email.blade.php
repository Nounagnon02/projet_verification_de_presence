<x-guest-layout>
    <div class="mb-4 text-sm text-ink-soft">
        {{ __("Merci pour votre inscription. Avant de commencer, vérifiez votre adresse email en cliquant sur le lien que nous venons de vous envoyer. Si vous ne l'avez pas reçu, nous vous en renverrons un.") }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-confirm">
            {{ __("Un nouveau lien de vérification a été envoyé à l'adresse email fournie lors de l'inscription.") }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __("Renvoyer l'email de vérification") }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-ink-soft hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-focus">
                {{ __('Déconnexion') }}
            </button>
        </form>
    </div>
</x-guest-layout>
