<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('ajout') }}">
                        @csrf

                        <h2 class="text-2xl font-bold mb-6 text-center">Ajouter un membre</h2>

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nom et Prénoms')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        

                        <!-- Phone number -->
                        <div class="mb-4">
                            <x-input-label for="phone" :value="__('Téléphone')" />
                            <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone')" required />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>


                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button class="ms-4">
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
