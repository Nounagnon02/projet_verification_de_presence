<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marquer sa présence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-4 px-3 sm:py-12 sm:px-6 lg:px-8">
        <div class="max-w-sm sm:max-w-md w-full space-y-6 sm:space-y-8">
            <!-- En-tête -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 sm:h-16 sm:w-16 bg-blue-500 rounded-full flex items-center justify-center mb-3 sm:mb-4">
                    <svg class="h-6 w-6 sm:h-8 sm:w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Marquer sa présence</h2>
                @if($qrCode->event_name)
                    <p class="text-base sm:text-lg text-gray-600 mb-2">{{ $qrCode->event_name }}</p>
                @endif
                <p class="text-sm text-gray-500">{{ $qrCode->event_date->format('d/m/Y') }}</p>
            </div>

            <!-- Carte de formulaire -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-4 py-6 sm:px-6 sm:py-8">
                    <form id="presenceForm" class="space-y-4 sm:space-y-6">
                        @csrf
                        
                        <!-- Validation du téléphone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Numéro de téléphone *
                            </label>
                            <input type="tel" name="phone" required 
                                   placeholder="Votre numéro de téléphone"
                                   class="w-full px-3 py-2 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <p class="text-xs text-gray-500 mt-1">Saisissez votre numéro pour vous identifier</p>
                        </div>

                        <!-- Section signature -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Signature (optionnel)
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-2 sm:p-4 bg-gray-50">
                                <canvas id="signatureCanvas" class="w-full h-24 sm:h-32 bg-white border border-gray-200 rounded cursor-crosshair touch-none"></canvas>
                                <button type="button" onclick="clearSignature()" class="mt-2 text-xs sm:text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                    🗑️ Effacer la signature
                                </button>
                            </div>
                        </div>

                        <!-- Bouton de soumission -->
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 sm:py-4 px-4 rounded-md transition-colors duration-200 flex items-center justify-center space-x-2 text-sm sm:text-base">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Confirmer ma présence</span>
                        </button>
                    </form>

                    <!-- Zone de message -->
                    <div id="message" class="mt-6 text-center hidden"></div>
                </div>
            </div>

            <!-- Pied de page -->
            <div class="text-center text-xs text-gray-500">
                Système de vérification de présence
            </div>
        </div>
    </div>

    <script>
        // Configuration du canvas de signature
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;

        // Définir la taille du canvas correctement pour le design responsive
        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.scale(ratio, ratio);
            canvas.style.width = rect.width + 'px';
            canvas.style.height = rect.height + 'px';
        }
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Écouteurs d'événements pour le dessin
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('touchstart', handleTouch);
        canvas.addEventListener('touchmove', handleTouch);
        canvas.addEventListener('touchend', stopDrawing);

        function handleTouch(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 
                                            e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        }

        function startDrawing(e) {
            isDrawing = true;
            hasSignature = true;
            draw(e);
        }

        function draw(e) {
            if (!isDrawing) return;
            
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const x = (e.clientX - rect.left) * scaleX / (window.devicePixelRatio || 1);
            const y = (e.clientY - rect.top) * scaleY / (window.devicePixelRatio || 1);
            
            ctx.lineWidth = window.innerWidth < 640 ? 3 : 2; // Plus épais sur mobile
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#1f2937';
            
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function stopDrawing() {
            if (!isDrawing) return;
            isDrawing = false;
            ctx.beginPath();
        }

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSignature = false;
        }
        
        // Générer l'empreinte de l'appareil
        function generateDeviceFingerprint() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Device fingerprint', 2, 2);
            
            const fingerprint = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                canvas.toDataURL()
            ].join('|');
            
            // Fonction de hachage simple
            let hash = 0;
            for (let i = 0; i < fingerprint.length; i++) {
                const char = fingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convertir en entier 32 bits
            }
            return Math.abs(hash).toString(36);
        }

        // Soumission du formulaire avec état de chargement
        document.getElementById('presenceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Afficher l'état de chargement
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Enregistrement...
            `;
            
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('phone', document.querySelector('input[name="phone"]').value);
            
            // Récupérer les données de signature si elles existent
            if (hasSignature) {
                const signatureData = canvas.toDataURL();
                formData.append('signature', signatureData);
            }
            
            // Générer l'empreinte de l'appareil
            const deviceFingerprint = generateDeviceFingerprint();
            formData.append('device_fingerprint', deviceFingerprint);
            
            // Fonction pour sauvegarder en local
            const saveOffline = (dataObject) => {
                const scans = JSON.parse(localStorage.getItem('offline_scans') || '[]');
                scans.push({
                    timestamp: Date.now(),
                    data: dataObject,
                    url: '{{ route("qr.presence", $qrCode->code) }}'
                });
                localStorage.setItem('offline_scans', JSON.stringify(scans));
                
                const messageDiv = document.getElementById('message');
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center';
                messageDiv.innerHTML = `
                    <div class="flex items-center justify-center space-x-2 text-yellow-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold">Mode Hors Ligne</span>
                    </div>
                    <p class="text-sm text-yellow-700 mt-2">
                        Scan sauvegardé dans le téléphone.<br>
                        Il sera envoyé automatiquement dès le retour de la connexion.
                    </p>
                    <p class="text-xs text-yellow-600 mt-4">Vous pouvez fermer cette page ou scanner d'autres personnes.</p>
                `;
                document.getElementById('presenceForm').reset();
                clearSignature();
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            };

            // Fonction pour envoyer les données
            const sendData = () => {
                // Préparer l'objet de données pour le stockage potentiel (FormData n'est pas stringifiable)
                const dataObject = {
                    _token: document.querySelector('meta[name="csrf-token"]').content,
                    phone: formData.get('phone'),
                    signature: formData.get('signature'),
                    device_fingerprint: formData.get('device_fingerprint'),
                    latitude: formData.get('latitude'),
                    longitude: formData.get('longitude'),
                    accuracy: formData.get('accuracy')
                };

                if (!navigator.onLine) {
                    saveOffline(dataObject);
                    return;
                }

                fetch('{{ route("qr.presence", $qrCode->code) }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const messageDiv = document.getElementById('message');
                    messageDiv.classList.remove('hidden');
                    
                    if (data.success) {
                        messageDiv.className = 'mt-6 p-4 bg-green-50 border border-green-200 rounded-lg text-center';
                        messageDiv.innerHTML = `
                            <div class="flex items-center justify-center space-x-2 text-green-800">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="font-semibold">${data.message}</span>
                            </div>
                            <p class="text-sm text-green-600 mt-2">Vous pouvez fermer cette page</p>
                        `;
                        document.getElementById('presenceForm').style.display = 'none';
                    } else {
                        // Si erreur serveur, afficher l'erreur
                        throw new Error(data.error || 'Erreur inconnue');
                    }
                })
                .catch(error => {
                    console.warn('Echec envoi, tentative sauvegarde offline', error);
                    // En cas d'échec réseau (pas d'erreur 400/500 explicite du serveur mais échec fetch)
                    // On sauvegarde en mode hors ligne
                    saveOffline(dataObject);
                });
            };

            // Tenter de récupérer la localisation
            if ("geolocation" in navigator) {
                submitBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Localisation...
                `;
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        formData.append('latitude', position.coords.latitude);
                        formData.append('longitude', position.coords.longitude);
                        formData.append('accuracy', position.coords.accuracy);
                        sendData();
                    },
                    function(error) {
                        console.warn("Erreur géolocalisation:", error.message);
                        sendData();
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 3000, // Délai d'attente réduit pour ne pas bloquer
                        maximumAge: 0
                    }
                );
            } else {
                sendData();
            }
        });

        // Gestionnaire de synchronisation
        window.addEventListener('online', syncOfflineScans);
        
        // Tenter une synchronisation au chargement si on est en ligne
        if (navigator.onLine) {
            setTimeout(syncOfflineScans, 1000);
        }

        function syncOfflineScans() {
            const scans = JSON.parse(localStorage.getItem('offline_scans') || '[]');
            if (scans.length === 0) return;

            console.log(`Tentative de synchronisation de ${scans.length} scans...`);
            
            // Afficher un petit toast ou indicateur
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center';
            toast.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Synchronisation de ${scans.length} scan(s)...
            `;
            document.body.appendChild(toast);

            // Traiter séquentiellement
            const processNext = async (index) => {
                if (index >= scans.length) {
                    localStorage.removeItem('offline_scans');
                    toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                    toast.textContent = '✅ Synchronisation terminée !';
                    setTimeout(() => toast.remove(), 3000);
                    return;
                }

                const scan = scans[index];
                const formData = new FormData();
                // Reconstruire le FormData
                for (const key in scan.data) {
                    if (scan.data[key] !== null) {
                        formData.append(key, scan.data[key]);
                    }
                }

                try {
                    const response = await fetch(scan.url, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success || response.status === 429) { // 429 = déjà scanné, on considère comme traité
                        console.log(`Scan ${index + 1} synchronisé`);
                        processNext(index + 1);
                    } else {
                        console.error(`Erreur synchro scan ${index + 1}:`, data);
                        // On continue quand même pour ne pas bloquer les autres
                        processNext(index + 1);
                    }
                } catch (e) {
                    console.error('Erreur réseau durant synchro', e);
                    // On arrête la synchronisation, on garde tout pour plus tard
                    toast.className = 'fixed bottom-4 right-4 bg-red-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                    toast.textContent = '❌ Erreur connexion. Réessai plus tard.';
                    setTimeout(() => toast.remove(), 3000);
                }
            };

            processNext(0);
        }
    </script>
</body>
</html>