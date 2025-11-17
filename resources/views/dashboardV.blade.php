<x-app-layout>
    <div class="py-6 sm:py-8 md:py-12 lg:py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 md:px-8">
            <!-- Messages de feedback -->
            @if(session('verification_result'))
                <div class="mb-6 p-4 sm:p-6 bg-green-50 border-l-4 border-green-400 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-green-700 font-medium">{{ session('verification_result') }}</p>
                    </div>
                </div>
            @endif

            @if(session('verification_error'))
                <div class="mb-6 p-4 sm:p-6 bg-red-50 border-l-4 border-red-400 rounded-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-red-700 font-medium">{{ session('verification_error') }}</p>
                    </div>
                </div>
            @endif

            <x-auth-session-status class="mb-6" :status="session('status')" />

            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100">
                <div class="px-6 py-8 sm:px-8 sm:py-10 md:px-12 md:py-14">
                    <div class="text-center mb-8 sm:mb-10">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2">Vérifier la présence</h1>
                        <p class="text-gray-600 text-sm sm:text-base md:text-lg">Confirmez la présence d'un membre aujourd'hui</p>
                    </div>

                    <form method="POST" action="{{ route('verif') }}" class="max-w-lg mx-auto">
                        @csrf

                        <!-- Name -->
                        <div class="mb-8 sm:mb-10">
                            <x-input-label for="nometprenoms" :value="__('Nom et Prénoms')" class="text-sm sm:text-base font-semibold text-gray-700 mb-2" />
                            <x-text-input id="nometprenoms" 
                                class="block w-full text-sm sm:text-base py-3 sm:py-4 px-4 border-2 border-gray-200 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all" 
                                type="text" 
                                name="nometprenoms" 
                                :value="old('nometprenoms')" 
                                placeholder="Entrez le nom complet du membre"
                                required 
                                autofocus />
                            <x-input-error :messages="$errors->get('nometprenoms')" class="mt-2 text-sm" />
                            <p class="mt-2 text-xs sm:text-sm text-gray-500">Tapez le nom exact tel qu'enregistré dans le système</p>
                        </div>

                        <div class="flex justify-center">
                            <x-primary-button class="w-full sm:w-auto px-8 sm:px-12 py-3 sm:py-4 text-base sm:text-lg font-semibold bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 rounded-lg transition-all transform hover:scale-105 focus:scale-105">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ __('Vérifier la présence') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
