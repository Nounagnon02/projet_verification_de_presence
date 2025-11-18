<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Vérifier la Présence') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('verification_result'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('verification_result') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Liste des Membres - {{ now()->format('d/m/Y') }}</h3>
                    
                    @if($members->count() > 0)
                        <form method="POST" action="{{ route('verif') }}">
                            @csrf
                            <div class="space-y-3 mb-6">
                                @foreach($members as $member)
                                    <div class="flex items-center p-3 border rounded-lg {{ in_array($member->id, $presencesToday) ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' }}">
                                        <input type="checkbox" 
                                               name="presences[]" 
                                               value="{{ $member->id }}"
                                               id="member_{{ $member->id }}"
                                               {{ in_array($member->id, $presencesToday) ? 'checked' : '' }}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="member_{{ $member->id }}" class="ml-3 flex-1 cursor-pointer">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-gray-900">{{ $member->name }}</span>
                                                <span class="text-xs text-gray-500">{{ $member->phone }}</span>
                                            </div>
                                        </label>
                                        @if(in_array($member->id, $presencesToday))
                                            <span class="ml-2 text-xs text-green-600 font-medium">Présent</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <p class="text-sm text-gray-600">{{ $members->count() }} membre(s) au total</p>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                                    Enregistrer les présences
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-gray-500 text-center py-8">Aucun membre enregistré dans votre groupe.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
