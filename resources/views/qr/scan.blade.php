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
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-blue-500 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Marquer sa présence</h2>
                @if($qrCode->event_name)
                    <p class="text-lg text-gray-600 mb-2">{{ $qrCode->event_name }}</p>
                @endif
                <p class="text-sm text-gray-500">{{ $qrCode->event_date->format('d/m/Y') }}</p>
            </div>

            <!-- Form Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-8">
                    <form id="presenceForm" class="space-y-6">
                        @csrf
                        
                        <!-- Member Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Sélectionnez votre nom
                            </label>
                            <select name="member_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <option value="" class="text-gray-500">-- Choisir votre nom --</option>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Signature Section -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Signature (optionnel)
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50">
                                <canvas id="signatureCanvas" width="300" height="120" class="w-full bg-white border border-gray-200 rounded cursor-crosshair"></canvas>
                                <button type="button" onclick="clearSignature()" class="mt-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                    🗑️ Effacer la signature
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-md transition-colors duration-200 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Confirmer ma présence</span>
                        </button>
                    </form>

                    <!-- Message Area -->
                    <div id="message" class="mt-6 text-center hidden"></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center text-xs text-gray-500">
                Système de vérification de présence
            </div>
        </div>
    </div>

    <script>
        // Signature canvas setup
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;

        // Set canvas size properly
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * window.devicePixelRatio;
        canvas.height = rect.height * window.devicePixelRatio;
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';

        // Event listeners for drawing
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
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            ctx.lineWidth = 2;
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

        // Form submission with loading state
        document.getElementById('presenceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
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
            formData.append('member_id', document.querySelector('select[name="member_id"]').value);
            
            // Get signature data if exists
            if (hasSignature) {
                const signatureData = canvas.toDataURL();
                formData.append('signature', signatureData);
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
                    messageDiv.className = 'mt-6 p-4 bg-red-50 border border-red-200 rounded-lg text-center';
                    messageDiv.innerHTML = `
                        <div class="flex items-center justify-center space-x-2 text-red-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="font-semibold">${data.error || 'Erreur lors de l\'enregistrement'}</span>
                        </div>
                    `;
                    // Reset button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                const messageDiv = document.getElementById('message');
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'mt-6 p-4 bg-red-50 border border-red-200 rounded-lg text-center';
                messageDiv.innerHTML = `
                    <div class="flex items-center justify-center space-x-2 text-red-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold">Erreur de connexion</span>
                    </div>
                `;
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>