<x-public-layout :title="__('Système de vérification de présence')">

    <!-- Hero -->
    <section class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
        <h1 class="font-display font-semibold text-ink text-3xl sm:text-4xl md:text-5xl leading-tight">
            {!! __('La présence de vos groupes, <span class="text-accent">simplement</span> suivie') !!}
        </h1>
        <p class="mt-6 text-lg text-ink-soft max-w-2xl mx-auto leading-relaxed">
            {{ __("Un carnet de présence numérique pour les groupes de l'église : un QR personnel par membre, un scan par le responsable, un suivi qui se fait de lui-même.") }}
        </p>
        <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-7 py-3.5 rounded-lg bg-accent text-white font-bold text-base hover:bg-accent-hover transition-colors">
                    {{ __('Accéder au tableau de bord') }}
                </a>
            @else
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-7 py-3.5 rounded-lg bg-accent text-white font-bold text-base hover:bg-accent-hover transition-colors">
                    {{ __('Créer un compte') }}
                </a>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-7 py-3.5 rounded-lg border-[1.5px] border-line-strong bg-card text-ink font-bold text-base hover:bg-paper2 transition-colors">
                    {{ __('Se connecter') }}
                </a>
            @endauth
        </div>
    </section>

    <!-- Comment ça marche -->
    <section class="bg-paper2 border-y border-line py-16 sm:py-20">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-ink text-center mb-12">{{ __('Comment ça marche') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-card border border-line rounded-xl p-6">
                    <div class="w-10 h-10 rounded-lg bg-accent-tint text-accent flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.3 19c0-3.3 2.6-5.6 5.7-5.6s5.7 2.3 5.7 5.6" stroke-linecap="round"/><circle cx="17.3" cy="9" r="2.2"/><path d="M15.6 13.6c2.5.4 4.1 2.1 4.4 4.6" stroke-linecap="round"/></svg>
                    </div>
                    <h3 class="font-display font-semibold text-ink mb-2">{{ __('1. Chaque membre a un QR') }}</h3>
                    <p class="text-sm text-ink-soft leading-relaxed">{{ __("Une carte personnelle et imprimable qui identifie le membre, sans qu'il ait besoin d'un téléphone.") }}</p>
                </div>
                <div class="bg-card border border-line rounded-xl p-6">
                    <div class="w-10 h-10 rounded-lg bg-accent-tint text-accent flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                    </div>
                    <h3 class="font-display font-semibold text-ink mb-2">{{ __('2. Le responsable scanne') }}</h3>
                    <p class="text-sm text-ink-soft leading-relaxed">{{ __('Une session est ouverte pour la réunion ; le responsable scanne chaque QR au fur et à mesure.') }}</p>
                </div>
                <div class="bg-card border border-line rounded-xl p-6">
                    <div class="w-10 h-10 rounded-lg bg-accent-tint text-accent flex items-center justify-center mb-4">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19V11M12 19V5M19 19v-7"/></svg>
                    </div>
                    <h3 class="font-display font-semibold text-ink mb-2">{{ __('3. Le suivi se fait seul') }}</h3>
                    <p class="text-sm text-ink-soft leading-relaxed">{{ __("Statistiques, classement de régularité et carte d'assiduité se mettent à jour d'eux-mêmes, groupe par groupe.") }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Invitation finale -->
    <section class="max-w-4xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
        <h2 class="font-display font-semibold text-2xl sm:text-3xl text-ink mb-4">{{ __('Prêt à commencer ?') }}</h2>
        <p class="text-ink-soft mb-8">{{ __("La mise en place d'un groupe prend moins de deux minutes.") }}</p>
        @guest
            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-7 py-3.5 rounded-lg bg-accent text-white font-bold text-base hover:bg-accent-hover transition-colors">
                {{ __('Créer un compte') }}
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-7 py-3.5 rounded-lg bg-accent text-white font-bold text-base hover:bg-accent-hover transition-colors">
                {{ __('Accéder au tableau de bord') }}
            </a>
        @endguest
    </section>

</x-public-layout>
