<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('messages.verify_presence') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('verification_result'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('verification_result') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg transition-colors duration-300">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('messages.member_list') }} - {{ now()->format('d/m/Y') }}</h3>
                    
                    @if($members->count() > 0)
                        <form method="POST" action="{{ route('verif') }}">
                            @csrf
                            <div class="space-y-3 mb-6">
                                @foreach($members as $member)
                                    <div class="flex items-center p-3 border rounded-lg transition-colors duration-200 {{ in_array($member->id, $presencesToday) ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : 'bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600' }}">
                                        <input type="checkbox" 
                                               name="presences[]" 
                                               value="{{ $member->id }}"
                                               id="member_{{ $member->id }}"
                                               {{ in_array($member->id, $presencesToday) ? 'checked' : '' }}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="member_{{ $member->id }}" class="ml-3 flex-1 cursor-pointer">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $member->name }}</span>
                                                    <span class="block text-xs text-blue-600 dark:text-blue-400 font-mono">Code: {{ $member->member_code ?? 'N/A' }}</span>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $member->phone }}</span>
                                            </div>
                                        </label>
                                        @if(in_array($member->id, $presencesToday))
                                            <span class="ml-2 text-xs text-green-600 dark:text-green-400 font-medium">{{ __('messages.present') }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- Section signature -->
                            <div class="mb-6 p-4 border rounded-lg bg-gray-50 dark:bg-gray-700">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Signature de validation (optionnel)</label>
                                <canvas id="signatureCanvas" width="400" height="150" class="border border-gray-300 dark:border-gray-600 rounded w-full cursor-crosshair bg-white"></canvas>
                                <div class="mt-2 flex justify-between">
                                    <button type="button" onclick="clearSignature()" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">Effacer signature</button>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Signez pour valider les présences</span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('messages.members_total', ['count' => $members->count()]) }}</p>
                                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded">
                                    {{ __('messages.save_presences') }}
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-center py-8">{{ __('messages.no_members') }}</p>
                    @endif
                </div>
            </div>
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
        
        // Ajouter la signature au formulaire
        document.querySelector('form').addEventListener('submit', function(e) {
            const signatureData = canvas.toDataURL();
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'signature';
            input.value = signatureData;
            this.appendChild(input);
        });
    </script>
</x-app-layout>
