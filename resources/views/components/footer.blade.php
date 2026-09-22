<footer class="bg-paper2 border-t border-line mt-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 py-8 sm:py-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div>
                <h3 class="font-display font-semibold text-ink mb-4">Produit</h3>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('about') }}" class="hover:text-ink transition-colors">À propos</a></li>
                    <li><a href="{{ route('security') }}" class="hover:text-ink transition-colors">Sécurité</a></li>
                    <li><a href="{{ route('features') }}" class="hover:text-ink transition-colors">Fonctionnalités</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-display font-semibold text-ink mb-4">Support</h3>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('contact') }}" class="hover:text-ink transition-colors">Contact</a></li>
                    <li><a href="{{ route('documentation') }}" class="hover:text-ink transition-colors">Documentation</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-ink transition-colors">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-display font-semibold text-ink mb-4">Légal</h3>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('privacy') }}" class="hover:text-ink transition-colors">Confidentialité</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-ink transition-colors">CGU</a></li>
                    <li><a href="{{ route('rgpd.index') }}" class="hover:text-ink transition-colors">RGPD</a></li>
                </ul>
            </div>
        </div>
        <div class="text-center text-ink-soft border-t border-line pt-8">
            <p class="text-sm sm:text-base">&copy; {{ date('Y') }} Système de Vérification de Présence. Tous droits réservés.</p>
            <p class="text-xs mt-2">Hébergé de manière sécurisée · Conforme RGPD · Données chiffrées</p>
        </div>
    </div>
</footer>
