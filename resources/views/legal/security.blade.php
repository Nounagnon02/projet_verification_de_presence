<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('Sécurité') }}</h1>

                <x-legal-translation-notice />

                <div class="prose max-w-none space-y-6">
                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Mesures de sécurité') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-green-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-confirm mb-2 flex items-center gap-2"><x-icon name="lock" class="w-5 h-5" /> {{ __('Chiffrement') }}</h3>
                                <p class="text-sm">{{ $securityInfo['encryption'] }}</p>
                            </div>
                            <div class="bg-accent-tint p-4 rounded-lg">
                                <h3 class="font-semibold text-accent mb-2">{{ __('Cloisonnement') }}</h3>
                                <p class="text-sm">{{ __('Un responsable ne voit que les membres des groupes qu’il encadre.') }}</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Conformité') }}</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach($securityInfo['compliance'] as $standard)
                                <span class="bg-confirm-tint text-confirm px-3 py-1 rounded-full text-sm inline-flex items-center gap-1.5">
                                    <x-icon name="check" class="w-4 h-4" /> {{ $standard }}
                                </span>
                            @endforeach
                        </div>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold mb-3">{{ __('Signalement de vulnérabilité') }}</h2>
                        <p>
                            {{ __('Si vous découvrez une faille de sécurité, merci de nous contacter immédiatement pour que nous puissions la corriger rapidement.') }}
                        </p>
                    </section>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-accent hover:bg-accent-hover text-white px-6 py-2 rounded-lg">
                        {{ __("Retour à l'accueil") }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
