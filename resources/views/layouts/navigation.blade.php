<div x-data="{ sidebarOpen: false }" x-cloak>
    <!-- Barre mobile -->
    <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between gap-3 h-16 px-4 bg-paper2 border-b border-line">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <x-application-logo class="w-7 h-7" />
            <span class="text-xs font-bold uppercase tracking-widest text-ink-soft">Présence</span>
        </a>
        <button @click="sidebarOpen = true" class="p-2 rounded-lg text-ink-soft hover:bg-paper hover:text-ink transition-colors" aria-label="{{ __('Ouvrir le menu') }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Fond assombri (mobile) -->
    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="lg:hidden fixed inset-0 z-40 bg-ink/50" style="display: none;"></div>

    <!-- Sidebar -->
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed lg:sticky top-0 left-0 z-50 h-screen w-72 lg:w-64 flex-shrink-0 bg-paper2 border-r border-line flex flex-col px-4 py-6 overflow-y-auto transition-transform duration-200 ease-out">

        <!-- En-tête -->
        <div class="flex items-center justify-between gap-2 px-3 pb-5 mb-3 border-b border-line">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                <x-application-logo class="w-7 h-7" />
                <span class="text-xs font-bold uppercase tracking-widest text-ink-soft">Présence</span>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden p-1 text-ink-soft hover:text-ink" aria-label="{{ __('Fermer le menu') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex flex-col gap-1">
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5L12 4l8 6.5V19a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/></svg>
                {{ __('Tableau de bord') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('dashboardV')" :active="request()->routeIs('dashboardV')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="8.4"/></svg>
                {{ __('Vérifier la présence') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('membres')" :active="request()->routeIs('membres*')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.3 19c0-3.3 2.6-5.6 5.7-5.6s5.7 2.3 5.7 5.6" stroke-linecap="round"/><circle cx="17.3" cy="9" r="2.2"/><path d="M15.6 13.6c2.5.4 4.1 2.1 4.4 4.6" stroke-linecap="round"/></svg>
                {{ __('Membres') }}
            </x-sidebar-link>

            <div class="mt-5 mb-1 px-4 text-xs font-bold uppercase tracking-widest text-ink-faint">{{ __('Analyse') }}</div>
            <x-sidebar-link :href="route('statistiques')" :active="request()->routeIs('statistiques')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19V11M12 19V5M19 19v-7"/></svg>
                {{ __('Statistiques') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('statistiques.avancees')" :active="request()->routeIs('statistiques.avancees')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16l5-5 4 4 7-7" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 8h5v5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('Analyses avancées') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('heatmap.index')" :active="request()->routeIs('heatmap.*')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                {{ __('Assiduité (heatmap)') }}
            </x-sidebar-link>

            <div class="mt-5 mb-1 px-4 text-xs font-bold uppercase tracking-widest text-ink-faint">{{ __('Organisation') }}</div>
            @if(auth()->user()->groupsLed->isNotEmpty())
                <x-sidebar-link :href="route('alerts.index', auth()->user()->groupsLed->first())" :active="request()->routeIs('alerts.*')">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16v-5a6 6 0 0 1 12 0v5l1.6 2H4.4z" stroke-linejoin="round"/><path d="M10 20a2 2 0 0 0 4 0" stroke-linecap="round"/></svg>
                    {{ __('Suivi des absences') }}
                </x-sidebar-link>
            @endif
            <x-sidebar-link :href="route('rgpd.index')" :active="request()->routeIs('rgpd.*')">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.6-3 7.9-7 10-4-2.1-7-5.4-7-10V6z" stroke-linejoin="round"/><path d="M9.3 12.2l1.8 1.8 3.6-3.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('Confidentialité (RGPD)') }}
            </x-sidebar-link>
        </nav>

        <div class="flex-grow"></div>

        <div class="border-t border-line pt-3 mt-4 flex flex-col gap-1">
            <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                <div class="w-8 h-8 rounded-full bg-confirm text-white flex items-center justify-center font-display font-semibold text-sm flex-shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <span class="truncate">{{ Auth::user()->name }}</span>
            </x-sidebar-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <a href="{{ route('logout') }}"
                    onclick="event.preventDefault(); this.closest('form').submit();"
                    class="flex items-center gap-3.5 px-4 py-3 rounded-lg text-base font-bold leading-none text-ink-soft hover:bg-paper hover:text-accent transition-colors cursor-pointer">
                    <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    {{ __('Déconnexion') }}
                </a>
            </form>
        </div>
    </aside>
</div>
