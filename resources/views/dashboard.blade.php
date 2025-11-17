<x-app-layout>
    <div class="py-6 md:py-8 lg:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg md:rounded-xl">
                <div class="p-4 md:p-6 lg:p-8 text-gray-900">
                    <form method="POST" action="{{ route('ajout') }}">
                        @csrf

                        <h2 class="text-xl md:text-2xl lg:text-3xl font-bold mb-4 md:mb-6 text-center">Ajouter un membre</h2>

                        <div class="max-w-md mx-auto">
                            <!-- Name -->
                            <div class="mb-4 md:mb-6">
                                <x-input-label for="name" :value="__('Nom et Prénoms')" class="text-sm md:text-base" />
                                <x-text-input id="name" class="block mt-1 w-full text-sm md:text-base py-2 md:py-3" type="text" name="name" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Phone number -->
                            <div class="mb-4 md:mb-6">
                                <x-input-label for="phone" :value="__('Téléphone')" class="text-sm md:text-base" />
                                <x-text-input id="phone" class="block mt-1 w-full text-sm md:text-base py-2 md:py-3" type="text" name="phone" :value="old('phone')" required />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                        </div>


                        <div class="flex items-center justify-center mt-4 md:mt-6">
                            <x-primary-button class="px-6 md:px-8 py-2 md:py-3 text-sm md:text-base">
                                {{ __('Enregistrer') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Afficher les messages de succès/erreur -->
                    @if(session('success'))
                        <div class="mt-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mt-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
