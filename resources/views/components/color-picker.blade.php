<div class="relative">
    <button id="color-picker-btn" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" title="Personnaliser les couleurs">
        <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zM21 5a2 2 0 00-2-2h-4a2 2 0 00-2 2v12a4 4 0 004 4h4a2 2 0 002-2V5z"></path>
        </svg>
    </button>
    
    <div id="color-picker-menu" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
        <div class="p-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Thème de couleur</h4>
            <div class="grid grid-cols-4 gap-2">
                <button onclick="setColorTheme('blue')" class="w-8 h-8 bg-blue-500 rounded-full hover:scale-110 transition-transform" title="Bleu"></button>
                <button onclick="setColorTheme('green')" class="w-8 h-8 bg-green-500 rounded-full hover:scale-110 transition-transform" title="Vert"></button>
                <button onclick="setColorTheme('purple')" class="w-8 h-8 bg-purple-500 rounded-full hover:scale-110 transition-transform" title="Violet"></button>
                <button onclick="setColorTheme('red')" class="w-8 h-8 bg-red-500 rounded-full hover:scale-110 transition-transform" title="Rouge"></button>
                <button onclick="setColorTheme('yellow')" class="w-8 h-8 bg-yellow-500 rounded-full hover:scale-110 transition-transform" title="Jaune"></button>
                <button onclick="setColorTheme('pink')" class="w-8 h-8 bg-pink-500 rounded-full hover:scale-110 transition-transform" title="Rose"></button>
                <button onclick="setColorTheme('indigo')" class="w-8 h-8 bg-indigo-500 rounded-full hover:scale-110 transition-transform" title="Indigo"></button>
                <button onclick="setColorTheme('gray')" class="w-8 h-8 bg-gray-500 rounded-full hover:scale-110 transition-transform" title="Gris"></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const colorBtn = document.getElementById('color-picker-btn');
    const colorMenu = document.getElementById('color-picker-menu');
    
    colorBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        colorMenu.classList.toggle('hidden');
    });
    
    document.addEventListener('click', function() {
        colorMenu.classList.add('hidden');
    });
    
    // Appliquer le thème sauvegardé
    const savedTheme = localStorage.getItem('color-theme-accent') || 'blue';
    applyColorTheme(savedTheme);
});

function setColorTheme(color) {
    localStorage.setItem('color-theme-accent', color);
    applyColorTheme(color);
    document.getElementById('color-picker-menu').classList.add('hidden');
}

function applyColorTheme(color) {
    const root = document.documentElement;
    
    // Supprimer les anciennes classes de couleur
    const colors = ['blue', 'green', 'purple', 'red', 'yellow', 'pink', 'indigo', 'gray'];
    colors.forEach(c => {
        root.classList.remove(`theme-${c}`);
    });
    
    // Ajouter la nouvelle classe de couleur
    root.classList.add(`theme-${color}`);
}
</script>

<style>
:root.theme-blue { --color-primary: #3b82f6; --color-primary-dark: #2563eb; }
:root.theme-green { --color-primary: #10b981; --color-primary-dark: #059669; }
:root.theme-purple { --color-primary: #8b5cf6; --color-primary-dark: #7c3aed; }
:root.theme-red { --color-primary: #ef4444; --color-primary-dark: #dc2626; }
:root.theme-yellow { --color-primary: #f59e0b; --color-primary-dark: #d97706; }
:root.theme-pink { --color-primary: #ec4899; --color-primary-dark: #db2777; }
:root.theme-indigo { --color-primary: #6366f1; --color-primary-dark: #4f46e5; }
:root.theme-gray { --color-primary: #6b7280; --color-primary-dark: #4b5563; }

.btn-primary {
    background-color: var(--color-primary);
}
.btn-primary:hover {
    background-color: var(--color-primary-dark);
}
</style>