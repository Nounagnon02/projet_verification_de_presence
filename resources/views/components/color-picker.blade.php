<div class="relative">
    <button id="color-picker-btn" class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
        <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zM7 3V1m0 18v2m8-10h2m-2 0h2m-2 0v2m0-2v-2"></path>
        </svg>
    </button>
    
    <div id="color-picker-menu" class="hidden absolute right-0 mt-2 w-32 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-2">
        <div class="grid grid-cols-4 gap-2">
            <button class="w-6 h-6 rounded bg-blue-500 hover:scale-110 transition-transform" data-color="blue"></button>
            <button class="w-6 h-6 rounded bg-green-500 hover:scale-110 transition-transform" data-color="green"></button>
            <button class="w-6 h-6 rounded bg-purple-500 hover:scale-110 transition-transform" data-color="purple"></button>
            <button class="w-6 h-6 rounded bg-red-500 hover:scale-110 transition-transform" data-color="red"></button>
        </div>
    </div>
</div>

<script>
document.getElementById('color-picker-btn').addEventListener('click', function() {
    document.getElementById('color-picker-menu').classList.toggle('hidden');
});
</script>