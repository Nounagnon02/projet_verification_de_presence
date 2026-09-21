<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Session — {{ $session->group->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-4">
                        Ouverte le {{ $session->opened_at->format('d/m/Y à H:i') }}
                        @if($session->event_name) — {{ $session->event_name }} @endif
                    </p>

                    <div id="scanner-region" class="w-full rounded-lg overflow-hidden border"></div>

                    <div id="scan-feedback"></div>

                    <p class="text-xs text-gray-500 mt-4">
                        Scannez le QR personnel de chaque membre présent. Le résultat s'affiche ci-dessus après chaque scan.
                    </p>

                    <form method="POST" action="{{ route('sessions.close', $session) }}" class="mt-6">
                        @csrf
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                                onclick="return confirm('Fermer la session ?')">
                            Fermer la session
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/scanner.js'])
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.initAttendanceScanner(
                'scanner-region',
                @json(route('sessions.scan.submit', $session)),
                @json(csrf_token()),
                'scan-feedback'
            );
        });
    </script>
</x-app-layout>
