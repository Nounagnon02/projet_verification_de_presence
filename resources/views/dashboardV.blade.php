<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink dark:text-white leading-tight">
            {{ __('messages.verify_presence') }}
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
                <div class="bg-card dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg transition-colors duration-300 mb-6">
                    <div class="p-6 text-ink dark:text-gray-100">
                        <h3 class="text-lg font-medium text-ink dark:text-white mb-4">{{ $group->name }} - {{ now()->translatedFormat(__('date.short')) }}</h3>

                        @if($group->members->count() > 0)
                            <form method="POST" action="{{ route('verif', $group) }}">
                                @csrf
                                <div class="space-y-3 mb-6">
                                    @foreach($group->members as $member)
                                        <div class="flex items-center p-3 border rounded-lg transition-colors duration-200 {{ in_array($member->id, $presencesToday) ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : 'bg-paper dark:bg-gray-700 border-line dark:border-gray-600' }}">
                                            <input type="checkbox"
                                                   name="presences[]"
                                                   value="{{ $member->id }}"
                                                   id="member_{{ $group->id }}_{{ $member->id }}"
                                                   {{ in_array($member->id, $presencesToday) ? 'checked' : '' }}
                                                   class="h-4 w-4 text-accent focus:ring-accent-focus border-line-strong rounded">
                                            <label for="member_{{ $group->id }}_{{ $member->id }}" class="ml-3 flex-1 cursor-pointer">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-medium text-ink dark:text-gray-100">{{ $member->name }}</span>
                                                    <span class="text-xs text-ink-faint dark:text-gray-400">{{ $member->phone }}</span>
                                                </div>
                                            </label>
                                            @if(in_array($member->id, $presencesToday))
                                                <span class="ml-2 text-xs text-confirm dark:text-green-400 font-medium">{{ __('messages.present') }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-between items-center">
                                    <p class="text-sm text-ink-soft dark:text-gray-400">{{ __('messages.members_total', ['count' => $group->members->count()]) }}</p>
                                    <button type="submit" class="bg-accent text-white font-bold py-2 px-6 rounded">
                                        {{ __('messages.save_presences') }}
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-ink-faint dark:text-gray-400 text-center py-8">{{ __('messages.no_members') }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-ink-faint dark:text-gray-400 text-center py-8">Vous ne dirigez aucun groupe pour le moment.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
