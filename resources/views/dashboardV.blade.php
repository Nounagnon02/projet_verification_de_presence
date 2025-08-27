<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Session Status -->
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <form method="POST" action="{{ route('verif') }}">
                        @csrf

                        <h2 class="text-2xl font-bold mb-6 text-center">Vérifier la présence</h2>

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="nometprenoms" :value="__('Nom et Prénoms')" />
                            <x-text-input id="nometprenoms" class="block mt-1 w-full" type="text" name="nometprenoms" :value="old('nometprenoms')" required autofocus />
                            <x-input-error :messages="$errors->get('nometprenoms')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button class="ms-3">
                                {{ __('Vérifier') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Afficher le résultat de la vérification -->
                    @if(session('verification_result'))
                        <div class="mt-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ session('verification_result') }}
                        </div>
                    @endif

                    @if(session('verification_error'))
                        <div class="mt-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            {{ session('verification_error') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
