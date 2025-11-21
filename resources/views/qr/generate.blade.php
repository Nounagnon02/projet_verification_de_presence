<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            Générateur QR Code
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
                                       class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Nom de l'événement (optionnel)</label>
                                <input type="text" name="event_name" placeholder="Réunion, Formation..."
                                       class="w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            </div>
                        </div>
                        <button type="submit" class="mt-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Générer QR Code
                        </button>
                    </form>

                    @if(isset($qrCode))
                        <div class="text-center">
                            <h3 class="text-lg font-semibold mb-4">QR Code généré</h3>
                            <div class="bg-white p-4 inline-block rounded">
                                {!! $qrImage !!}
                            </div>
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                Code: {{ $qrCode->code }}<br>
                                Valide jusqu'à: {{ $qrCode->expires_at->format('d/m/Y H:i') }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500">
                                URL: {{ route('qr.scan', $qrCode->code) }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>