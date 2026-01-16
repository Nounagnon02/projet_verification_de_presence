@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Boutique de Récompenses</h1>
            <p class="text-gray-600 mt-2">Échangez vos points contre des avantages exclusifs !</p>
        </div>
        <div class="bg-indigo-600 text-white px-6 py-3 rounded-full shadow-lg flex items-center gap-2">
            <span class="text-xl font-bold">💎 {{ auth()->user()->member->points ?? 0 }} pts</span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($rewards as $reward)
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                @if($reward->image_url)
                    <img src="{{ $reward->image_url }}" alt="{{ $reward->name }}" class="w-full h-48 object-cover">
                @else
                    <div class="w-full h-48 bg-gradient-to-r from-indigo-500 to-purple-600 flex items-center justify-center">
                        <span class="text-6xl">🎁</span>
                    </div>
                @endif

                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-xl font-bold text-gray-900">{{ $reward->name }}</h3>
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded border border-yellow-200">
                            {{ $reward->cost }} pts
                        </span>
                    </div>
                    
                    <p class="text-gray-600 mb-6 text-sm h-12 overflow-hidden">{{ $reward->description }}</p>

                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            @if($reward->stock > 0 || $reward->stock == -1)
                                <span class="text-green-600 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    En stock
                                </span>
                            @else
                                <span class="text-red-500">Épuisé</span>
                            @endif
                        </div>

                        <form action="{{ route('rewards.redeem', $reward->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="member_id" value="{{ auth()->user()->member->id ?? '' }}">
                            <button type="submit" 
                                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                                    {{ (auth()->user()->member->points ?? 0) < $reward->cost || ($reward->stock == 0) ? 'disabled' : '' }}>
                                Échanger
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <p class="text-gray-500 text-lg">Aucune récompense disponible pour le moment.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
