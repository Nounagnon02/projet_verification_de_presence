<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Vérifier la présence') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('verification_result'))
                <div class="mb-6 bg-confirm-tint border border-confirm/30 text-confirm px-4 py-3 rounded">
                    {{ session('verification_result') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @forelse($groups as $group)
                <div class="bg-card overflow-hidden shadow-sm sm:rounded-lg transition-colors duration-300 mb-6">
                    <div class="p-6 text-ink">
                        <h3 class="text-lg font-medium text-ink mb-4">{{ $group->name }} - {{ now()->translatedFormat(__('date.short')) }}</h3>

                        @if($group->members->count() > 0)
                            <form method="POST" action="{{ route('verif', $group) }}">
                                @csrf
                                <div class="space-y-3 mb-6">
                                    @foreach($group->members as $member)
                                        <div class="flex items-center p-3 border rounded-lg transition-colors duration-200 {{ in_array($member->id, $presencesToday) ? 'bg-green-50 border-green-200 ' : 'bg-paper border-line ' }}">
                                            <input type="checkbox"
                                                   name="presences[]"
                                                   value="{{ $member->id }}"
                                                   id="member_{{ $group->id }}_{{ $member->id }}"
                                                   {{ in_array($member->id, $presencesToday) ? 'checked' : '' }}
                                                   class="h-4 w-4 text-accent focus:ring-accent-focus border-line-strong rounded">
                                            <label for="member_{{ $group->id }}_{{ $member->id }}" class="ml-3 flex-1 cursor-pointer">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-medium text-ink">{{ $member->name }}</span>
                                                    <span class="text-xs text-ink-faint">{{ $member->phone }}</span>
                                                </div>
                                            </label>
                                            @if(in_array($member->id, $presencesToday))
                                                <span class="ml-2 text-xs text-confirm font-medium">{{ __('Présent') }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-between items-center">
                                    <p class="text-sm text-ink-soft">{{ trans_choice(':count membre au total|:count membres au total', $group->members->count()) }}</p>
                                    <button type="submit" class="bg-accent text-white font-bold py-2 px-6 rounded">
                                        {{ __('Enregistrer les présences') }}
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-ink-faint text-center py-8">{{ __('Aucun membre enregistré dans ce groupe.') }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-ink-faint text-center py-8">{{ __('Vous ne dirigez aucun groupe pour le moment.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
