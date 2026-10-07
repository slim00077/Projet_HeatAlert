@extends('layouts.front')

@section('title', 'Alertes Chaleur en cours')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-gray-800">Alertes Chaleur</h1>
        <p class="text-gray-600 mt-2">Consultez les alertes chaleur en cours dans votre quartier.</p>
    </div>

    <x-flash-messages />

    @auth
        <div class="mb-6 flex justify-center">
            <a href="{{ auth()->user()->isAdmin() ? route('admin.alerts.create') : route('front.alerts.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Ajouter une alerte</a>
        </div>
    @endauth

    <!-- Filtre par quartier -->
    <div class="mb-8 flex justify-center">
        <form action="{{ route('front.alerts.index') }}" method="GET" class="flex items-center space-x-2">
            <select name="neighborhood_id" class="border rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Tous les quartiers</option>
                @foreach($neighborhoods as $neighborhood)
                    <option value="{{ $neighborhood->id }}" {{ request('neighborhood_id') == $neighborhood->id ? 'selected' : '' }}>
                        {{ $neighborhood->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filtrer</button>
        </form>
    </div>

    <!-- Liste des alertes -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($alerts as $alert)
            <div class="bg-white rounded-lg shadow-md overflow-hidden border-t-4 
                {{ $alert->severity_level == 'high' ? 'border-red-500' : ($alert->severity_level == 'medium' ? 'border-yellow-500' : 'border-green-500') }}">
                @if($alert->imageUrl())
                    <img src="{{ $alert->imageUrl() }}" alt="{{ $alert->title }}" class="w-full h-40 object-cover" loading="lazy">
                @endif
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h2 class="text-xl font-bold text-gray-800">{{ $alert->title }}</h2>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full 
                            {{ $alert->severity_level == 'high' ? 'bg-red-100 text-red-800' : ($alert->severity_level == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                            {{ ucfirst($alert->severity_level) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mb-2">
                        <span class="font-semibold">Quartier:</span> {{ $alert->neighborhood->name ?? 'N/A' }}
                    </p>
                    @if($alert->residence_name)
                        <p class="text-sm text-gray-600 mb-2">
                            <span class="font-semibold">Résidence:</span> {{ $alert->residence_name }}
                        </p>
                    @endif
                    <p class="text-gray-700 mb-4">{{ $alert->description }}</p>
                    
                    <div class="text-sm text-gray-500">
                        <p><strong>Début:</strong> {{ $alert->start_date->format('d/m/Y à H:i') }}</p>
                        @if($alert->end_date)
                            <p><strong>Fin prévue:</strong> {{ $alert->end_date->format('d/m/Y à H:i') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center py-12 bg-gray-50 rounded-lg">
                <p class="text-gray-500 text-lg">Aucune alerte chaleur en cours. Tout va bien !</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
