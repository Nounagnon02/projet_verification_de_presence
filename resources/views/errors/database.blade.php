<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service indisponible</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans text-gray-900 antialiased bg-gray-100">
    <div class="min-h-screen flex flex-col justify-center items-center px-4">
        <div class="text-center">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Service temporairement indisponible</h1>
            <p class="text-gray-600 mb-8 max-w-md mx-auto">
                Nous rencontrons actuellement des difficultés techniques. 
                Veuillez réessayer dans quelques instants.
            </p>
            <a href="{{ route('welcome') }}" class="inline-flex items-center px-6 py-3 bg-gray-800 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Retour à l'accueil
            </a>
        </div>
    </div>
</body>
</html>