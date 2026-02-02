<footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 py-6 sm:py-8 md:py-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Produit</h3>
                <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                    <li><a href="{{ route('about') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">À propos</a></li>
                    <li><a href="{{ route('security') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">Sécurité</a></li>
                    <li><a href="{{ route('features') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">Fonctionnalités</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Support</h3>
                <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                    <li><a href="{{ route('contact') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">Contact</a></li>
                    <li><a href="{{ route('documentation') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">Documentation</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Légal</h3>
                <ul class="space-y-2 text-gray-600 dark:text-gray-400">
                    <li><a href="{{ route('privacy') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">Confidentialité</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">CGU</a></li>
                    <li><a href="{{ route('rgpd.index') }}" class="hover:text-gray-900 dark:hover:text-white transition-colors">RGPD</a></li>
                </ul>
            </div>
        </div>
        <div class="text-center text-gray-600 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-8">
            <p class="text-sm sm:text-base">&copy; {{ date('Y') }} Système de Vérification de Présence. Tous droits réservés.</p>
            <p class="text-xs mt-2">Hébergé de manière sécurisée • Conforme RGPD • Données chiffrées</p>
        </div>
    </div>
</footer>