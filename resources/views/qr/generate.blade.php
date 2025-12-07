<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            📱 Générateur QR Code
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <form method="POST" class="mb-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Date de l'événement</label>
                                <input type="date" name="date" value="{{ today()->format('Y-m-d') }}" 
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Nom de l'événement (optionnel)</label>
                                <input type="text" name="event_name" placeholder="Réunion, Formation..."
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <button type="submit" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                            Générer QR Code
                        </button>
                    </form>

                    @if(isset($qrCode))
                        <div class="text-center">
                            <!-- Timer circulaire -->
                            <div class="mb-6">
                                <div class="relative inline-flex items-center justify-center">
                                    <svg class="w-24 h-24 transform -rotate-90" viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="45" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                                        <circle id="timer-circle" cx="50" cy="50" r="45" fill="none" stroke="#3b82f6" stroke-width="8" 
                                                stroke-dasharray="283" stroke-dashoffset="0" stroke-linecap="round"
                                                class="transition-all duration-1000"/>
                                    </svg>
                                    <div class="absolute flex flex-col items-center">
                                        <span id="timer-seconds" class="text-2xl font-bold text-gray-900 dark:text-white">60</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">sec</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    Le QR code se renouvelle automatiquement
                                </p>
                            </div>

                            <h3 class="text-lg font-semibold mb-4 flex items-center justify-center">
                                <span class="w-3 h-3 bg-green-500 rounded-full animate-pulse mr-2"></span>
                                QR Code Actif
                            </h3>
                            
                            <div class="bg-white p-6 inline-block rounded-xl shadow-lg border-4 border-blue-500" id="qr-container">
                                {!! $qrImage !!}
                            </div>

                            @if($qrCode->event_name)
                                <p class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                                    {{ $qrCode->event_name }}
                                </p>
                            @endif

                            <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg inline-block">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="text-left">
                                        <span class="text-gray-500 dark:text-gray-400">Date:</span>
                                        <span class="font-medium text-gray-900 dark:text-white ml-2">
                                            {{ $qrCode->event_date->format('d/m/Y') }}
                                        </span>
                                    </div>
                                    <div class="text-left">
                                        <span class="text-gray-500 dark:text-gray-400">Expire:</span>
                                        <span class="font-medium text-gray-900 dark:text-white ml-2">
                                            {{ $qrCode->expires_at->format('H:i') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-2 text-left">
                                    <span class="text-gray-500 dark:text-gray-400">Dernière mise à jour:</span>
                                    <span id="last-update" class="font-medium text-blue-600 dark:text-blue-400 ml-2">
                                        {{ now()->format('H:i:s') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Barre de progression -->
                            <div class="mt-6 max-w-md mx-auto">
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                    <span>Validité du code</span>
                                    <span id="progress-text">100%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                    <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full transition-all duration-1000" style="width: 100%"></div>
                                </div>
                            </div>

                            <!-- Info sécurité -->
                            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg max-w-md mx-auto">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="text-left text-sm text-blue-700 dark:text-blue-300">
                                        <p class="font-medium">🔒 Sécurité Anti-Fraude</p>
                                        <p class="mt-1">Ce QR code change automatiquement chaque minute pour éviter toute utilisation frauduleuse.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                            let secondsRemaining = 60;
                            const totalSeconds = 60;
                            const circumference = 2 * Math.PI * 45; // 283

                            function updateTimer() {
                                // Mettre à jour le compteur
                                document.getElementById('timer-seconds').textContent = secondsRemaining;
                                
                                // Mettre à jour le cercle
                                const offset = circumference - (secondsRemaining / totalSeconds) * circumference;
                                document.getElementById('timer-circle').style.strokeDashoffset = offset;
                                
                                // Mettre à jour la barre de progression
                                const progressPercent = (secondsRemaining / totalSeconds) * 100;
                                document.getElementById('progress-bar').style.width = progressPercent + '%';
                                document.getElementById('progress-text').textContent = Math.round(progressPercent) + '%';
                                
                                // Changer la couleur quand il reste peu de temps
                                const circle = document.getElementById('timer-circle');
                                if (secondsRemaining <= 10) {
                                    circle.style.stroke = '#ef4444'; // Rouge
                                } else if (secondsRemaining <= 20) {
                                    circle.style.stroke = '#f59e0b'; // Orange
                                } else {
                                    circle.style.stroke = '#3b82f6'; // Bleu
                                }
                                
                                secondsRemaining--;
                                
                                if (secondsRemaining < 0) {
                                    // Rafraîchir le QR code
                                    refreshQrCode();
                                    secondsRemaining = 60;
                                }
                            }

                            function refreshQrCode() {
                                fetch('{{ route("qr.refresh") }}')
                                    .then(response => response.json())
                                    .then(data => {
                                        document.getElementById('qr-container').innerHTML = 
                                            '<img src="data:image/svg+xml;base64,' + data.qr_image + '" alt="QR Code" class="max-w-full">';
                                        document.getElementById('last-update').textContent = data.timestamp;
                                    })
                                    .catch(error => console.error('Erreur:', error));
                            }

                            // Démarrer le timer
                            setInterval(updateTimer, 1000);
                            updateTimer(); // Appel initial
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>