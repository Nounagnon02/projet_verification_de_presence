<footer class="bg-paper2 border-t border-line mt-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-10 py-8 sm:py-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div>
                <h3 class="font-display font-semibold text-ink mb-4">{{ __('Produit') }}</h3>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('about') }}" class="hover:text-ink transition-colors">{{ __('À propos') }}</a></li>
                    <li><a href="{{ route('security') }}" class="hover:text-ink transition-colors">{{ __('Sécurité') }}</a></li>
                    <li><a href="{{ route('features') }}" class="hover:text-ink transition-colors">{{ __('Fonctionnalités') }}</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-display font-semibold text-ink mb-4">{{ __('Support') }}</h3>
                <ul class="space-y-2 text-ink-soft">
                    <li><a href="{{ route('contact') }}" class="hover:text-ink transition-colors">{{ __('Contact') }}</a></li>
                    <li><a href="{{ route('documentation') }}" class="hover:text-ink transition-colors">{{ __('Documentation') }}</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-ink transition-colors">{{ __('FAQ') }}</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-display font-semibold text-ink mb-4">{{ __('Légal') }}</h3>
                <ul class="space-y-2 text-ink-soft">
                    {{-- Le lien « RGPD » pointait vers rgpd.index, une route protégée :
                         un visiteur non connecté était renvoyé vers la page de connexion. --}}
                    <li><a href="{{ route('privacy') }}" class="hover:text-ink transition-colors">{{ __('Confidentialité') }}</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-ink transition-colors">{{ __('CGU') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="text-center text-ink-soft border-t border-line pt-8">
            <p class="text-sm sm:text-base">&copy; {{ date('Y') }} {{ __('Système de vérification de présence. Tous droits réservés.') }}</p>
            <p class="text-xs mt-2">{{ __('Hébergement sécurisé · Conforme RGPD · Données chiffrées en transit') }}</p>
        </div>
    </div>
</footer>
