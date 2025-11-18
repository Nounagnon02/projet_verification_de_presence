<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR - Présence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold text-center mb-6">Marquer sa présence</h1>
            
            @if($qrCode->event_name)
                <p class="text-center text-gray-600 mb-4">{{ $qrCode->event_name }}</p>
            @endif
            
            <p class="text-center text-sm text-gray-500 mb-6">{{ $qrCode->event_date->format('d/m/Y') }}</p>

            <form id="presenceForm">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sélectionnez votre nom</label>
                    <select name="member_id" required class="w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">-- Choisir --</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Signature (optionnel)</label>
                    <canvas id="signatureCanvas" width="300" height="150" class="border border-gray-300 rounded w-full cursor-crosshair"></canvas>
                    <button type="button" onclick="clearSignature()" class="mt-2 text-sm text-blue-600 hover:text-blue-800">Effacer</button>
                </div>

                <button type="submit" class="w-full bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Confirmer ma présence
                </button>
            </form>

            <div id="message" class="mt-4 text-center hidden"></div>
        </div>
    </div>

    <script>
        // Signature canvas
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            isDrawing = true;
            draw(e);
        }

        function draw(e) {
            if (!isDrawing) return;
            
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.touches[0].clientX) - rect.left;
            const y = (e.clientY || e.touches[0].clientY) - rect.top;
            
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';
            
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
        }

        // Form submission
        document.getElementById('presenceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('member_id', document.querySelector('select[name="member_id"]').value);
            
            // Get signature data
            const signatureData = canvas.toDataURL();
            if (signatureData !== canvas.toDataURL('image/png', 0)) {
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
                    messageDiv.className = 'mt-4 text-center text-green-600 font-semibold';
                    messageDiv.textContent = data.message;
                    document.getElementById('presenceForm').style.display = 'none';
                } else {
                    messageDiv.className = 'mt-4 text-center text-red-600 font-semibold';
                    messageDiv.textContent = data.error || 'Erreur lors de l\'enregistrement';
                }
            })
            .catch(error => {
                const messageDiv = document.getElementById('message');
                messageDiv.classList.remove('hidden');
                messageDiv.className = 'mt-4 text-center text-red-600 font-semibold';
                messageDiv.textContent = 'Erreur de connexion';
            });
        });
    </script>
</body>
</html>