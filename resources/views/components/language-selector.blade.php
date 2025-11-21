<div class="relative">
    <button id="language-selector-btn" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors flex items-center space-x-1" title="Changer de langue">
        <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
        </svg>
        <span class="text-xs font-medium text-gray-600 dark:text-gray-300 uppercase">{{ app()->getLocale() }}</span>
    </button>
    
    <div id="language-selector-menu" class="hidden absolute right-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
        <div class="py-2">
            <a href="{{ route('language.switch', 'fr') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() == 'fr' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}">
                <span class="mr-3">🇫🇷</span>
                Français
            </a>
            <a href="{{ route('language.switch', 'en') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() == 'en' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}">
                <span class="mr-3">🇺🇸</span>
                English
            </a>
            <a href="{{ route('language.switch', 'es') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() == 'es' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : '' }}">
                <span class="mr-3">🇪🇸</span>
                Español
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const langBtn = document.getElementById('language-selector-btn');
    const langMenu = document.getElementById('language-selector-menu');
    
    langBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        langMenu.classList.toggle('hidden');
    });
    
    document.addEventListener('click', function() {
        langMenu.classList.add('hidden');
    });
});
</script>