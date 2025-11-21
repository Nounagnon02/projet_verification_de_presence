<div id="shortcuts-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">{{ __('messages.keyboard_shortcuts') }}</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">A</span>
                <span class="text-gray-900 dark:text-white">{{ __('messages.add_member_shortcut') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">V</span>
                <span class="text-gray-900 dark:text-white">{{ __('messages.verify_presence_shortcut') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">S</span>
                <span class="text-gray-900 dark:text-white">{{ __('messages.statistics_shortcut') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600 dark:text-gray-400">D</span>
                <span class="text-gray-900 dark:text-white">{{ __('messages.dark_mode_shortcut') }}</span>
            </div>
        </div>
        <button onclick="hideShortcuts()" class="mt-4 w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors">
            {{ __('messages.close') }}
        </button>
    </div>
</div>

<script>
function showShortcuts() {
    document.getElementById('shortcuts-modal').classList.remove('hidden');
}

function hideShortcuts() {
    document.getElementById('shortcuts-modal').classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === '?') {
        showShortcuts();
    }
    if (e.key === 'Escape') {
        hideShortcuts();
    }
});
</script>