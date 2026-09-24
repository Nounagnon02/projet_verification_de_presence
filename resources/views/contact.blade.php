<x-guest-layout>
    <div class="min-h-screen bg-paper py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-card rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-ink mb-6">{{ __('Contact') }}</h1>

                <div class="space-y-6">
                    <div class="bg-accent-tint p-6 rounded-lg">
                        <h2 class="text-xl font-semibold mb-4">{{ __('Nous contacter') }}</h2>
                        <p class="mb-4">{{ __('Pour toute question, suggestion ou support technique :') }}</p>
                        <ul class="space-y-2">
                            <li class="flex flex-col sm:flex-row sm:items-center">
                                <span class="font-medium">{{ __('Email :') }}</span>
                                <span class="break-all sm:ml-2 text-accent">princekangbode@gmail.com</span>
                            </li>
                            <li class="flex flex-col sm:flex-row sm:items-center">
                                <span class="font-medium">{{ __('Téléphone :') }}</span>
                                <span class="sm:ml-2">+229 01 90 11 24 77</span>
                            </li>
                            <li class="flex flex-col sm:flex-row sm:items-center">
                                <span class="font-medium">{{ __('Horaires :') }}</span>
                                <span class="sm:ml-2">{{ __('Lundi au vendredi, 9 h - 18 h') }}</span>
                            </li>
                        </ul>
                    </div>
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
