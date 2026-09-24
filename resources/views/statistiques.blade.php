<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl md:text-[28px] text-ink">{{ __('Statistiques de présence') }}</h1>
        <p class="text-ink-soft text-base mt-1">{{ __('Consultez et analysez les données de présence.') }}</p>
    </x-slot>

    <!-- Formulaire de filtrage -->
    <div class="bg-card rounded-xl border border-line mb-8">
        <div class="p-6">
            <h2 class="text-lg text-ink mb-5">{{ __('Filtres de recherche') }}</h2>
            <form method="GET" action="{{ route('statistiques') }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Filtre par date -->
                    <div>
                        <x-input-label for="date" class="mb-2">{{ __('Date') }}</x-input-label>
                        <x-text-input id="date" type="date" name="date" :value="$date" />
                    </div>

                    <!-- Recherche par nom ou téléphone -->
                    <div>
                        <x-input-label for="search" class="mb-2">{{ __('Rechercher') }}</x-input-label>
                        <x-text-input id="search" type="text" name="search" :value="$search" :placeholder="__('Nom ou téléphone')" />
                    </div>

                    <!-- Bouton de filtre -->
                    <div class="sm:col-span-2 lg:col-span-2 flex items-end">
                        <x-primary-button type="submit" class="w-full">
                            {{ __('Filtrer les résultats') }}
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <div class="p-6 rounded-xl border border-line bg-card">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-accent-tint flex items-center justify-center text-accent flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="font-display font-semibold text-ink">{{ __('Présents') }}</h3>
            </div>
            <p class="text-3xl font-display font-semibold text-ink">{{ $totalPresent }}</p>
        </div>

        <div class="p-6 rounded-xl border border-line bg-card">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-confirm-tint flex items-center justify-center text-confirm flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                </div>
                <h3 class="font-display font-semibold text-ink">{{ __('Total membres') }}</h3>
            </div>
            <p class="text-3xl font-display font-semibold text-ink">{{ $totalMembres }}</p>
        </div>

        <div class="p-6 rounded-xl border border-line bg-card sm:col-span-2 lg:col-span-1">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-accent-tint flex items-center justify-center text-accent flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="font-display font-semibold text-ink">{{ __('Taux de présence') }}</h3>
            </div>
            <p class="text-3xl font-display font-semibold text-ink">{{ $tauxPresence }}%</p>
        </div>
    </div>

    <!-- Liste des présences -->
    <div class="bg-card rounded-xl border border-line overflow-hidden">
        <div class="px-6 py-5 border-b border-line">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h3 class="text-lg text-ink">{{ __('Liste des présences') }}</h3>
                <a href="{{ route('statistiques', array_merge(request()->all(), ['export' => 'pdf'])) }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border-[1.5px] border-line-strong bg-card font-bold text-sm text-ink hover:bg-paper2 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('Exporter PDF') }}
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-paper">
                    <tr>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Nom') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-left text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Téléphone') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-center text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Heure') }}</th>
                        <th class="py-4 px-4 sm:px-6 text-center text-xs sm:text-sm font-semibold text-ink-soft uppercase tracking-wider">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-card divide-y divide-line">
                            @forelse($presences as $presence)
                                <tr class="hover:bg-paper transition-colors">
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base font-medium text-ink">
                                        {{ $presence->member->name }}
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-ink-soft">
                                        {{ $presence->member->phone }}
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-confirm-tint text-confirm">
                                            {{ \Carbon\Carbon::parse($presence->time)->translatedFormat(__('date.time')) }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 sm:px-6 text-sm sm:text-base text-center text-ink-soft">
                                        {{ \Carbon\Carbon::parse($presence->date)->translatedFormat(__('date.short')) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-ink-faint mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <p class="text-ink-faint text-lg font-medium">{{ __('Aucune présence enregistrée') }}</p>
                                            <p class="text-ink-faint text-sm">{{ __('Aucun membre n\'est présent pour cette date.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Audit Trail -->
            @if(isset($auditLogs) && $auditLogs->count() > 0)
            <div class="mt-8 bg-card rounded-xl border border-line overflow-hidden">
                <div class="px-6 py-4 border-b border-line">
                    <h3 class="text-lg font-semibold text-ink flex items-center">
                        <svg class="w-5 h-5 mr-2 text-ink-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        {{ __('Activités récentes') }}
                    </h3>
                </div>
                <div class="p-6">
                    @foreach($auditLogs as $log)
                        @php
                            // Phrase complète par action : une phrase assemblée par
                            // concaténation ne se traduit pas correctement.
                            $auditSentence = match ($log->action) {
                                'created' => __(':user a créé une présence', ['user' => $log->user->name ?? __('Système')]),
                                'updated' => __(':user a modifié une présence', ['user' => $log->user->name ?? __('Système')]),
                                default => __(':user a supprimé une présence', ['user' => $log->user->name ?? __('Système')]),
                            };
                        @endphp
                        <div class="flex items-center justify-between py-2 border-b border-line last:border-0">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-accent rounded-full mr-3"></div>
                                <span class="text-sm text-ink-soft">{{ $auditSentence }}</span>
                            </div>
                            <span class="text-xs text-ink-faint">{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
</x-app-layout>
