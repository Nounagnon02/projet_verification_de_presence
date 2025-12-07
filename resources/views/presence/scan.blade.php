<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pointage - {{ config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-center mb-6">Scanner QR Code</h1>
            
            <!-- Caméra -->
            <div class="mb-6">
                <video id="qr-video" class="w-full rounded-lg"></video>
                <div id="cam-qr-result" class="mt-2 text-center text-sm text-gray-600"></div>
            </div>

            <!-- Statut géolocalisation -->
            <div id="location-status" class="mb-4 p-3 rounded-lg bg-yellow-100 text-yellow-800">
                <div class="flex items-center">
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-yellow-600 mr-2"></div>
                    Obtention de votre position...
                </div>
            </div>

            <!-- Sélection membre -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Sélectionner le membre</label>
                <select id="member-select" class="w-full p-2 border border-gray-300 rounded-lg">
                    <option value="">Choisir un membre...</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Bouton de pointage -->
            <button id="submit-presence" 
                    class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-medium disabled:bg-gray-400"
                    disabled>
                Enregistrer la présence
            </button>

            <!-- Messages -->
            <div id="message" class="mt-4 hidden"></div>
        </div>
    </div>

    <script>
        let currentLocation = null;
        let scannedQrCode = null;
        
        // Scanner QR Code
        const video = document.getElementById('qr-video');
        const qrScanner = new QrScanner(video, result => {
            scannedQrCode = result.data;
            document.getElementById('cam-qr-result').textContent = 'QR Code détecté ✓';
            checkFormReady();
        });

        // Géolocalisation
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        currentLocation = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        };
                        
                        document.getElementById('location-status').innerHTML = 
                            '<div class="flex items-center text-green-800 bg-green-100 p-3 rounded-lg">' +
                            '<span class="mr-2">📍</span> Position obtenue (±' + Math.round(position.coords.accuracy) + 'm)' +
                            '</div>';
                        
                        checkFormReady();
                    },
                    error => {
                        document.getElementById('location-status').innerHTML = 
                            '<div class="text-red-800 bg-red-100 p-3 rounded-lg">' +
                            '❌ Impossible d\'obtenir votre position. Activez la géolocalisation.' +
                            '</div>';
                    }
                );
            }
        }

        function checkFormReady() {
            const memberSelected = document.getElementById('member-select').value;
            const submitBtn = document.getElementById('submit-presence');
            
            if (currentLocation && scannedQrCode && memberSelected) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('bg-gray-400');
                submitBtn.classList.add('bg-blue-600');
            }
        }

        // Soumission
        document.getElementById('submit-presence').addEventListener('click', async () => {
            const memberId = document.getElementById('member-select').value;
            
            try {
                const response = await fetch('/api/presence', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        qr_code: scannedQrCode,
                        member_id: memberId,
                        latitude: currentLocation.latitude,
                        longitude: currentLocation.longitude,
                        accuracy: currentLocation.accuracy
                    })
                });

                const data = await response.json();
                const messageDiv = document.getElementById('message');
                
                if (data.success) {
                    messageDiv.className = 'mt-4 p-3 bg-green-100 text-green-800 rounded-lg';
                    messageDiv.textContent = data.message;
                } else {
                    messageDiv.className = 'mt-4 p-3 bg-red-100 text-red-800 rounded-lg';
                    messageDiv.textContent = data.message;
                }
                
                messageDiv.classList.remove('hidden');
                
            } catch (error) {
                console.error('Erreur:', error);
            }
        });

        // Initialisation
        document.getElementById('member-select').addEventListener('change', checkFormReady);
        qrScanner.start();
        getLocation();
    </script>
</body>
</html>