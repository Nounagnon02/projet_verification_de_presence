<x-app-layout>
    <div class="py-6 sm:py-8 md:py-12 lg:py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 md:px-8">
            <!-- Messages de feedback -->
            @if(session('success'))
                <div class="mb-6 p-4 sm:p-6 bg-green-50 border-l-4 border-green-400 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-green-700 font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 sm:p-6 bg-red-50 border-l-4 border-red-400 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-red-700 font-medium">{{ session('error') }}</p>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100">
                <div class="px-6 py-8 sm:px-8 sm:py-10 md:px-12 md:py-14">
                    <div class="text-center mb-8 sm:mb-10">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2">Ajouter un membre</h1>
                        <p class="text-gray-600 text-sm sm:text-base md:text-lg">Enregistrez un nouveau membre dans le système</p>
                    </div>

                    <form method="POST" action="{{ route('ajout') }}" class="max-w-lg mx-auto">
                        @csrf

                        <!-- Name -->
                        <div class="mb-6 sm:mb-8">
                            <x-input-label for="name" :value="__('Nom et Prénoms')" class="text-sm sm:text-base font-semibold text-gray-700 mb-2" />
                            <x-text-input id="name" 
                                class="block w-full text-sm sm:text-base py-3 sm:py-4 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all" 
                                type="text" 
                                name="name" 
                                :value="old('name')" 
                                placeholder="Entrez le nom complet"
                                required 
                                autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2 text-sm" />
                        </div>

                        <!-- Phone number -->
                        <div class="mb-8 sm:mb-10">
                            <x-input-label for="phone" :value="__('Téléphone')" class="text-sm sm:text-base font-semibold text-gray-700 mb-2" />
                            <x-text-input id="phone" 
                                class="block w-full text-sm sm:text-base py-3 sm:py-4 px-4 border-2 border-gray-200 rounded-lg focus:border-gray-500 focus:ring-2 focus:ring-gray-200 transition-all" 
                                type="tel" 
                                name="phone" 
                                :value="old('phone')" 
                                placeholder="Ex: +33 6 12 34 56 78"
                                required />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2 text-sm" />
                        </div>

                        <div class="flex justify-center">
                            <x-primary-button class="w-full sm:w-auto px-8 sm:px-12 py-3 sm:py-4 text-base sm:text-lg font-semibold bg-gray-800 hover:bg-gray-700 focus:bg-gray-700 rounded-lg transition-all transform hover:scale-105 focus:scale-105">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Enregistrer le membre') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
