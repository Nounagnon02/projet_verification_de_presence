<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Modifier le Membre') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-card overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-ink">
                    
                    @if($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('membres.update', $membre->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-ink-soft">
                                Nom et Prénoms
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $membre->name) }}"
                                   class="mt-1 block w-full rounded-md border-line-strong shadow-sm focus:border-accent focus:ring-accent-focus"
                                   required>
                        </div>

                        <div class="mb-6">
                            <label for="phone" class="block text-sm font-medium text-ink-soft">
                                Numéro de Téléphone
                            </label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   value="{{ old('phone', $membre->phone) }}"
                                   class="mt-1 block w-full rounded-md border-line-strong shadow-sm focus:border-accent focus:ring-accent-focus"
                                   required>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-ink-soft mb-1">Groupe(s)</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach($groups as $group)
                                    <label class="flex items-center bg-paper border rounded px-3 py-2">
                                        <input type="checkbox" name="group_ids[]" value="{{ $group->id }}"
                                               {{ in_array($group->id, old('group_ids', $membreGroupIds->all())) ? 'checked' : '' }}
                                               class="rounded border-line-strong text-accent">
                                        <span class="ml-2 text-sm text-ink-soft">{{ $group->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('membres') }}" 
                               class="bg-gray-500 hover:bg-accent-hover text-white font-bold py-2 px-4 rounded">
                                Annuler
                            </a>
                            <button type="submit" 
                                    class="bg-accent hover:bg-accent-hover text-white font-bold py-2 px-4 rounded">
                                Mettre à jour
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>