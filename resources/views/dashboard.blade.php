<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ajouter des Membres') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Ajouter des membres</h3>
                    
                    <form method="POST" action="{{ route('ajout.multiple') }}" id="membersForm">
                        @csrf
                        <div id="membersContainer">
                            <div class="member-row border rounded-lg p-4 mb-4 bg-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom et Prénoms</label>
                                        <input type="text" name="members[0][name]" class="w-full border-gray-300 rounded-md" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                                        <input type="text" name="members[0][phone]" class="w-full border-gray-300 rounded-md" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center mt-6">
                            <button type="button" id="addMemberBtn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Ajouter un membre
                            </button>
                            
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                                Enregistrer tous les membres
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let memberIndex = 1;
        
        document.getElementById('addMemberBtn').addEventListener('click', function() {
            const container = document.getElementById('membersContainer');
            const newMemberRow = document.createElement('div');
            newMemberRow.className = 'member-row border rounded-lg p-4 mb-4 bg-gray-50';
            newMemberRow.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom et Prénoms</label>
                        <input type="text" name="members[${memberIndex}][name]" class="w-full border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="text" name="members[${memberIndex}][phone]" class="w-full border-gray-300 rounded-md" required>
                    </div>
                </div>
                <button type="button" class="remove-member mt-2 text-red-600 hover:text-red-800 text-sm" onclick="this.parentElement.remove()">
                    Supprimer ce membre
                </button>
            `;
            container.appendChild(newMemberRow);
            memberIndex++;
        });
    </script>
</x-app-layout>
