<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink dark:text-gray-200 leading-tight">
            {{ __('Liste des membres') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-confirm-tint dark:bg-green-900 border border-confirm/30 dark:border-green-600 text-confirm dark:text-green-300 px-4 py-3 rounded-lg mb-4 mx-2 md:mx-0">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg mb-4 mx-2 md:mx-0">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg mb-4 mx-2 md:mx-0">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mx-2 md:mx-0">
                <!-- Liste des membres -->
                <div class="lg:col-span-2">
                    <div class="bg-card dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg" x-data="{ showAddForm: {{ $errors->any() ? 'true' : 'false' }} }">
                        <div class="p-6 text-ink dark:text-gray-100">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h3 class="text-lg font-medium">{{ __('Gestion des membres') }}</h3>
                                    <p class="text-sm text-ink-soft dark:text-gray-400">{{ trans_choice('Total : :count membre|Total : :count membres', $membres->total()) }}</p>
                                </div>
                                <button type="button" @click="showAddForm = !showAddForm" class="bg-accent hover:bg-accent-hover text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                    <span x-text="showAddForm ? @js(__('Fermer')) : @js(__('+ Ajouter'))"></span>
                                </button>
                            </div>

                            <div x-show="showAddForm" x-cloak class="mb-6 border border-line rounded-lg p-6">
                                <h4 class="text-base font-medium text-ink mb-5">{{ __('Ajouter des membres') }}</h4>

                                <form method="POST" action="{{ route('ajout.multiple') }}" id="membersForm" class="space-y-6">
                                    @csrf

                                    <div>
                                        <x-input-label class="mb-2">{{ __('Groupe(s)') }}</x-input-label>
                                        <div class="flex flex-wrap gap-3">
                                            @forelse($groups as $group)
                                                <label class="flex items-center gap-2 bg-paper border border-line rounded-lg px-3 py-2 text-sm font-semibold text-ink">
                                                    <input type="checkbox" name="group_ids[]" value="{{ $group->id }}" class="rounded border-line-strong text-accent focus:ring-accent-focus">
                                                    {{ $group->name }}
                                                </label>
                                            @empty
                                                <p class="text-sm text-ink-soft">{{ __('Vous ne dirigez aucun groupe.') }}</p>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div id="membersContainer" class="space-y-4">
                                        <div class="member-row border border-line rounded-lg p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label class="mb-1.5">{{ __('Nom et prénoms') }}</x-input-label>
                                                    <x-text-input type="text" name="members[0][name]" required />
                                                </div>
                                                <div>
                                                    <x-input-label class="mb-1.5">{{ __('Téléphone') }}</x-input-label>
                                                    <x-text-input type="text" name="members[0][phone]" required />
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <label class="flex items-center gap-2 text-sm text-ink">
                                                    <input type="checkbox" name="members[0][rgpd_consent]" class="rounded border-line-strong text-accent focus:ring-accent-focus" required>
                                                    {{ __('Consentement RGPD obtenu (oral/écrit)') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 pt-2">
                                        <button type="button" id="addMemberBtn" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg border-[1.5px] border-line-strong bg-card font-bold text-base text-ink hover:bg-paper2 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            {{ __('Ajouter un membre') }}
                                        </button>

                                        <x-primary-button type="submit">{{ __('Enregistrer tous les membres') }}</x-primary-button>
                                    </div>
                                </form>
                            </div>

                            @if($membres->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-paper dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">
                                                    {{ __('Membre') }}
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">
                                                    {{ __('Téléphone') }}
                                                </th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">
                                                    {{ __('Groupes') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">
                                                    {{ __('Régularité') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-xs font-medium text-ink-faint dark:text-gray-300 uppercase tracking-wider">
                                                    {{ __('Actions') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-card dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($membres as $membre)
                                                @php
                                                    $memberScore = $scores[$membre->id] ?? ['score' => 0, 'stars' => 0, 'level' => 'critical', 'color' => 'gray', 'total_presences' => 0, 'total_events' => 0];
                                                @endphp
                                                <tr class="hover:bg-paper dark:hover:bg-gray-700 transition-colors">
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="w-10 h-10 bg-{{ $memberScore['color'] }}-100 dark:bg-{{ $memberScore['color'] }}-900/30 rounded-full flex items-center justify-center mr-3">
                                                                <span class="text-{{ $memberScore['color'] }}-600 dark:text-{{ $memberScore['color'] }}-400 font-semibold text-sm">
                                                                    {{ strtoupper(substr($membre->name, 0, 2)) }}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-medium text-ink dark:text-white">{{ $membre->name }}</div>
                                                                <div class="text-xs text-ink-faint dark:text-gray-400">
                                                                    {{ __(':done/:total événements', ['done' => $memberScore['total_presences'], 'total' => $memberScore['total_events']]) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-ink-soft dark:text-gray-300">
                                                        {{ $membre->phone }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-ink-soft dark:text-gray-300">
                                                        {{ $membre->groups->pluck('name')->implode(', ') }}
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <div class="flex flex-col items-center">
                                                            <!-- Score et étoiles -->
                                                            <div class="flex items-center mb-1">
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <svg class="w-4 h-4 {{ $i <= $memberScore['stars'] ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                                    </svg>
                                                                @endfor
                                                            </div>
                                                            <!-- Barre de progression -->
                                                            <div class="w-full bg-paper2 dark:bg-gray-600 rounded-full h-2">
                                                                <div class="bg-{{ $memberScore['color'] }}-500 h-2 rounded-full transition-all duration-500" style="width: {{ min($memberScore['score'], 100) }}%"></div>
                                                            </div>
                                                            <span class="text-xs font-semibold text-{{ $memberScore['color'] }}-600 dark:text-{{ $memberScore['color'] }}-400 mt-1">
                                                                {{ $memberScore['score'] }}%
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                                        <a href="{{ route('membres.print-card', $membre->id) }}"
                                                           target="_blank"
                                                           class="text-ink-soft dark:text-gray-300 hover:text-ink dark:hover:text-white mr-3"
                                                           title="{{ __('Carte QR') }}">
                                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 16h4.01M9 12h.01M12 16h.01M9 16h.01M4 12h.01M4 16h.01M4 4h4v4H4V4zm12 0h4v4h-4V4zM4 16h4v4H4v-4z"/>
                                                            </svg>
                                                        </a>
                                                        <a href="{{ route('membres.edit', $membre->id) }}"
                                                           class="text-accent dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 mr-3"
                                                           title="{{ __('Modifier') }}">
                                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </a>
                                                        <form action="{{ route('membres.delete', $membre->id) }}"
                                                              method="POST"
                                                              class="inline"
                                                              onsubmit="return confirm(@js(__('Êtes-vous sûr de vouloir supprimer ce membre ?')))">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                    class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                                                                    title="{{ __('Supprimer') }}">
                                                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-6">
                                    {{ $membres->links() }}
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <svg class="w-16 h-16 text-ink-faint mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p class="text-ink-faint dark:text-gray-400">{{ __('Aucun membre enregistré pour le moment.') }}</p>
                                    <button type="button" @click="showAddForm = true"
                                       class="mt-4 inline-flex items-center px-4 py-2 bg-accent rounded-lg font-semibold text-sm text-white hover:bg-accent-hover transition-colors">
                                        {{ __('Ajouter un membre') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Classement top 5 -->
                <div class="lg:col-span-1">
                    <div class="bg-card dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-ink dark:text-white mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/>
                                </svg>
                                {{ __('Top 5 régularité') }}
                            </h3>

                            @if(isset($ranking) && count($ranking) > 0)
                                <div class="space-y-3">
                                    @foreach($ranking as $index => $rank)
                                        <div class="flex items-center p-3 rounded-lg {{ $index === 0 ? 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800' : 'bg-paper dark:bg-gray-700' }}">
                                            <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full {{ $index === 0 ? 'bg-yellow-400 text-white' : ($index === 1 ? 'bg-gray-400 text-white' : ($index === 2 ? 'bg-orange-400 text-white' : 'bg-paper2 dark:bg-gray-600 text-ink-soft dark:text-gray-300')) }} font-bold text-sm">
                                                {{ $index + 1 }}
                                            </div>
                                            <div class="ml-3 flex-1 min-w-0">
                                                <p class="text-sm font-medium text-ink dark:text-white truncate">
                                                    {{ $rank['member']->name }}
                                                </p>
                                                <p class="text-xs text-ink-faint dark:text-gray-400">
                                                    {{ __(':done/:total événements', ['done' => $rank['presences'], 'total' => $rank['events']]) }}
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0 text-right">
                                                <span class="text-lg font-bold {{ $index === 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-ink dark:text-white' }}">
                                                    {{ $rank['score'] }}%
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 text-ink-faint dark:text-gray-400">
                                    <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                    </svg>
                                    <p>{{ __('Aucune donnée disponible') }}</p>
                                    <p class="text-sm mt-1">{{ __('Programmez des événements pour voir le classement') }}</p>
                                </div>
                            @endif

                            <!-- Légende des niveaux -->
                            <div class="mt-6 pt-4 border-t border-line dark:border-gray-700">
                                <p class="text-xs font-medium text-ink-faint dark:text-gray-400 mb-2">{{ __('Niveaux de régularité :') }}</p>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                        <span class="text-ink-soft dark:text-gray-300">{{ __('Excellent (≥90%)') }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                        <span class="text-ink-soft dark:text-gray-300">{{ __('Bon (≥70%)') }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                                        <span class="text-ink-soft dark:text-gray-300">{{ __('Moyen (≥50%)') }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-orange-500 rounded-full mr-2"></div>
                                        <span class="text-ink-soft dark:text-gray-300">{{ __('Faible (≥30%)') }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                        <span class="text-ink-soft dark:text-gray-300">{{ __('Critique (<30%)') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        // Le JS ne peut pas appeler __() : les libellés du gabarit de ligne
        // membre sont traduits côté serveur et injectés ci-dessous.
        // Le tableau est construit ici, pas dans @json(...) : la directive
        // Blade ne gère pas correctement un littéral sur plusieurs lignes.
        $memberRowLabels = [
            'name' => __('Nom et prénoms'),
            'phone' => __('Téléphone'),
            'consent' => __('Consentement RGPD obtenu (oral/écrit)'),
            'remove' => __('Supprimer ce membre'),
        ];
    @endphp

    <script>
        const memberRowLabels = @json($memberRowLabels);

        let memberIndex = 1;

        document.getElementById('addMemberBtn').addEventListener('click', function() {
            const container = document.getElementById('membersContainer');
            const newMemberRow = document.createElement('div');
            newMemberRow.className = 'member-row border border-line rounded-lg p-4';
            newMemberRow.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[15px] font-bold text-ink mb-1.5">${memberRowLabels.name}</label>
                        <input type="text" name="members[${memberIndex}][name]" class="w-full h-[52px] px-4 rounded-lg border-[1.5px] border-line bg-card text-ink text-base focus:border-accent" required>
                    </div>
                    <div>
                        <label class="block text-[15px] font-bold text-ink mb-1.5">${memberRowLabels.phone}</label>
                        <input type="text" name="members[${memberIndex}][phone]" class="w-full h-[52px] px-4 rounded-lg border-[1.5px] border-line bg-card text-ink text-base focus:border-accent" required>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="members[${memberIndex}][rgpd_consent]" class="rounded border-line-strong text-accent focus:ring-accent-focus" required>
                        ${memberRowLabels.consent}
                    </label>
                </div>
                <button type="button" class="remove-member mt-3 text-sm font-semibold text-red-700 hover:text-red-800" onclick="this.parentElement.remove()">
                    ${memberRowLabels.remove}
                </button>
            `;
            container.appendChild(newMemberRow);
            memberIndex++;
        });
    </script>
</x-app-layout>
