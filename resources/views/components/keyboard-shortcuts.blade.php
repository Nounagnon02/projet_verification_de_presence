<div id="shortcuts-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Raccourcis Clavier</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-300">Ajouter membre</span>
                    <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl + A</kbd>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-300">Vérifier présence</span>
                    <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl + V</kbd>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-300">Statistiques</span>
                    <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl + S</kbd>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-300">Mode sombre</span>
                    <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl + D</kbd>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-300">Aide</span>
                    <kbd class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">?</kbd>
                </div>
            </div>
            <button onclick="closeShortcuts()" class="mt-4 w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                Fermer
            </button>
        </div>
    </div>
</div>

<script>
function showShortcuts() {
    document.getElementById('shortcuts-modal').classList.remove('hidden');
}

function closeShortcuts() {
    document.getElementById('shortcuts-modal').classList.add('hidden');
}

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey) {
        switch(e.key) {
            case 'a':
                e.preventDefault();
                window.location.href = '{{ route("dashboard") }}';
                break;
            case 'v':
                e.preventDefault();
                window.location.href = '{{ route("dashboardV") }}';
                break;
            case 's':
                e.preventDefault();
                window.location.href = '{{ route("statistiques") }}';
                break;
            case 'd':
                e.preventDefault();
                document.getElementById('theme-toggle').click();
                break;
        }
    } else if (e.key === '?') {
        e.preventDefault();
        showShortcuts();
    }
});
</script>