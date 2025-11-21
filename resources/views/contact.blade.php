<x-guest-layout>
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Contact</h1>
                
                <div class="space-y-6">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h2 class="text-xl font-semibold mb-4">Nous contacter</h2>
                        <p class="mb-4">Pour toute question, suggestion ou support technique :</p>
                        <ul class="space-y-2">
                            <li>📧 Email : support@presence-app.com</li>
                            <li>📱 Téléphone : +33 1 23 45 67 89</li>
                            <li>🕒 Horaires : Lun-Ven 9h-18h</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-8">
                    <a href="{{ route('welcome') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>