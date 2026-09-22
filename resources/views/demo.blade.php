<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink dark:text-white leading-tight">
            Démonstration
        </h2>
    </x-slot>

    <div class="py-12">
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-display font-semibold text-ink mb-4">Démonstration du système</h1>
        <p class="text-lg text-ink-soft">Découvrez les fonctionnalités en action</p>
    </div>

    <div class="grid md:grid-cols-2 gap-8 mb-8">
        <!-- Capture d'écran Dashboard -->
        <div class="bg-card rounded-lg shadow-lg overflow-hidden">
            <div class="bg-accent p-4">
                <h3 class="text-white font-semibold">Dashboard Principal</h3>
            </div>
            <div class="p-6">
                <div class="bg-paper2 h-48 rounded flex items-center justify-center mb-4">
                    <div class="text-center text-ink-faint">
                        <svg class="w-10 h-10 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19V11M12 19V5M19 19v-7"/></svg>
                        <p>Interface de gestion</p>
                        <p class="text-sm">Statistiques en temps réel</p>
                    </div>
                </div>
                <ul class="text-sm text-ink-soft space-y-1">
                    <li>• Vue d'ensemble des présences</li>
                    <li>• Graphiques interactifs</li>
                    <li>• Export PDF automatique</li>
                </ul>
            </div>
        </div>

        <!-- QR Code Demo -->
        <div class="bg-card rounded-lg shadow-lg overflow-hidden">
            <div class="bg-accent p-4">
                <h3 class="text-white font-semibold">QR Code</h3>
            </div>
            <div class="p-6">
                <div class="bg-paper2 h-48 rounded flex items-center justify-center mb-4">
                    <div class="text-center text-ink-faint">
                        <svg class="w-10 h-10 mx-auto mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                        <p>QR Code généré</p>
                        <p class="text-sm">Scan rapide</p>
                    </div>
                </div>
                <ul class="text-sm text-ink-soft space-y-1">
                    <li>• Génération automatique</li>
                    <li>• Vérification instantanée</li>
                    <li>• Compatible mobile</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Workflow -->
    <div class="bg-paper2 border border-line p-8 rounded-lg mb-8">
        <h2 class="text-2xl font-display font-semibold mb-6 text-center">Processus simplifié</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="bg-card border border-line p-4 rounded-lg mb-3">
                    <div class="w-9 h-9 rounded-lg bg-accent-tint text-accent flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.6 3.1-6.2 7-6.2s7 2.6 7 6.2" stroke-linecap="round"/></svg>
                    </div>
                    <h3 class="font-display font-semibold">1. Inscription</h3>
                </div>
                <p class="text-sm text-ink-soft">Création de compte sécurisée</p>
            </div>
            <div class="text-center">
                <div class="bg-card border border-line p-4 rounded-lg mb-3">
                    <div class="w-9 h-9 rounded-lg bg-accent-tint text-accent flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                    </div>
                    <h3 class="font-display font-semibold">2. Vérification</h3>
                </div>
                <p class="text-sm text-ink-soft">Scan QR ou saisie manuelle</p>
            </div>
            <div class="text-center">
                <div class="bg-card border border-line p-4 rounded-lg mb-3">
                    <div class="w-9 h-9 rounded-lg bg-accent-tint text-accent flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 19V11M12 19V5M19 19v-7"/></svg>
                    </div>
                    <h3 class="font-display font-semibold">3. Suivi</h3>
                </div>
                <p class="text-sm text-ink-soft">Statistiques automatiques</p>
            </div>
            <div class="text-center">
                <div class="bg-card border border-line p-4 rounded-lg mb-3">
                    <div class="w-9 h-9 rounded-lg bg-accent-tint text-accent flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-display font-semibold">4. Export</h3>
                </div>
                <p class="text-sm text-ink-soft">Rapports PDF détaillés</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="text-center bg-card p-8 rounded-lg shadow-lg">
        <h2 class="text-2xl font-semibold mb-4">Prêt à commencer ?</h2>
        <p class="text-ink-soft mb-6">Créez votre compte gratuitement et testez toutes les fonctionnalités</p>
        <div class="space-x-4">
            @guest
                <a href="{{ route('register') }}" class="bg-accent text-white px-6 py-3 rounded-lg hover:bg-accent-hover transition-colors">
                    Créer un compte
                </a>
                <a href="{{ route('login') }}" class="border border-accent text-accent px-6 py-3 rounded-lg hover:bg-accent-tint transition-colors">
                    Se connecter
                </a>
            @else
                <a href="{{ route('dashboard') }}" class="bg-accent text-white px-6 py-3 rounded-lg hover:bg-accent-hover transition-colors">
                    Accéder au Dashboard
                </a>
            @endguest
        </div>
        </div>
    </div>
</x-app-layout>