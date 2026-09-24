<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" aria-label="{{ __('Retour au tableau de bord') }}" class="w-10 h-10 rounded-lg flex items-center justify-center text-ink hover:bg-paper2 transition-colors flex-shrink-0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-lg text-ink">{{ $session->group->name }}</h1>
                <p class="text-sm text-ink-soft">
                    {{ __('Session en cours · ouverte le :date', ['date' => $session->opened_at->translatedFormat(__('date.datetime'))]) }}
                    @if($session->event_name) — {{ $session->event_name }} @endif
                </p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 bg-confirm-tint border border-confirm/30 text-confirm font-semibold px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-md mx-auto rounded-xl overflow-hidden border border-line">
        <!-- Zone caméra -->
        <div class="bg-[#332C25] flex flex-col items-center justify-center gap-6 p-8">
            <div id="scanner-region" class="w-full max-w-[280px] aspect-square rounded-lg overflow-hidden"></div>
            <p class="text-[#F3ECDF] text-base text-center max-w-xs leading-relaxed">
                {{ __('Placez le QR du membre dans le cadre.') }}
            </p>
        </div>

        <!-- État du scan -->
        <div id="scan-waiting" class="px-5 py-6 bg-paper2" style="display:flex; align-items:center; gap:14px;">
            <span class="text-base text-ink-soft">{{ __('En attente d\'un scan…') }}</span>
        </div>
        <div id="scan-success" class="px-5 py-6 bg-confirm-tint" style="display:none; flex-direction:column; gap:6px;">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-confirm flex items-center justify-center flex-shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </div>
                <span id="scan-success-text" class="text-[17px] font-bold text-ink"></span>
            </div>
            <span id="scan-success-badge" class="text-sm text-confirm pl-[48px]"></span>
        </div>
        <div id="scan-error" class="px-5 py-6 bg-accent-tint" style="display:none; align-items:center; gap:12px;">
            <div class="w-9 h-9 rounded-full bg-accent flex items-center justify-center flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </div>
            <span id="scan-error-text" class="text-base font-bold text-ink"></span>
        </div>

        <!-- Fermer la session -->
        <div class="px-5 py-6 bg-card border-t border-line">
            <form method="POST" action="{{ route('sessions.close', $session) }}" onsubmit="return confirm(@js(__('Fermer la session ?')))">
                @csrf
                <x-secondary-button type="submit" class="w-full">{{ __('Fermer la session') }}</x-secondary-button>
            </form>
        </div>
    </div>

    @php
        // Le JavaScript ne peut pas appeler __() : les textes du scanner sont
        // traduits ici et passés au module. Le tableau est construit dans ce
        // bloc et non dans @json(...), que Blade compile mal sur plusieurs lignes.
        $scannerLabels = [
            'badgeEarned' => __('Badge obtenu :'),
            'unknownError' => __('Erreur inconnue.'),
            'networkError' => __('Erreur réseau, réessayez.'),
            'cameraError' => __("Impossible d'accéder à la caméra. Vérifiez les autorisations du navigateur."),
        ];
    @endphp

    @vite(['resources/js/scanner.js'])
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.initAttendanceScanner(
                'scanner-region',
                @json(route('sessions.scan.submit', $session)),
                @json(csrf_token()),
                @json($scannerLabels)
            );
        });
    </script>
</x-app-layout>
