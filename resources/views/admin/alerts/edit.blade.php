@extends('layouts.back')

@section('title', 'Modifier une Alerte Chaleur')

@section('content')
<div class="container mx-auto px-4 max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Modifier l'Alerte: {{ $heatAlert->title }}</h1>
    </div>

    <form action="{{ route('admin.alerts.update', $heatAlert) }}" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf
        @method('PUT')
        
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="neighborhood_id">Quartier</label>
            <select class="shadow border @error('neighborhood_id') border-red-500 @enderror rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="neighborhood_id" name="neighborhood_id" required>
                <option value="">Sélectionnez un quartier</option>
                @foreach($neighborhoods as $neighborhood)
                    <option value="{{ $neighborhood->id }}" {{ old('neighborhood_id', $heatAlert->neighborhood_id) == $neighborhood->id ? 'selected' : '' }}>
                        {{ $neighborhood->name }}
                    </option>
                @endforeach
            </select>
            @error('neighborhood_id') <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="title">Titre</label>
            <input class="shadow appearance-none border @error('title') border-red-500 @enderror rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none" id="title" type="text" name="title" value="{{ old('title', $heatAlert->title) }}" required>
            @error('title') <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
            <textarea class="shadow appearance-none border @error('description') border-red-500 @enderror rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none" id="description" name="description" rows="3">{{ old('description', $heatAlert->description) }}</textarea>
            @error('description') <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4 flex space-x-4">
            <div class="w-1/2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="severity_level">Sévérité</label>
                <select class="shadow border @error('severity_level') border-red-500 @enderror rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none" id="severity_level" name="severity_level" required>
                    <option value="low" {{ old('severity_level', $heatAlert->severity_level) == 'low' ? 'selected' : '' }}>Faible</option>
                    <option value="medium" {{ old('severity_level', $heatAlert->severity_level) == 'medium' ? 'selected' : '' }}>Moyenne</option>
                    <option value="high" {{ old('severity_level', $heatAlert->severity_level) == 'high' ? 'selected' : '' }}>Élevée</option>
                </select>
                @error('severity_level') <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mb-6 flex space-x-4">
            <div class="w-1/2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="start_date">Date de Début</label>
                <input class="shadow appearance-none border @error('start_date') border-red-500 @enderror rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none" id="start_date" type="datetime-local" name="start_date" value="{{ old('start_date', $heatAlert->start_date ? $heatAlert->start_date->format('Y-m-d\TH:i') : '') }}" required>
                @error('start_date') <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p> @enderror
            </div>
            <div class="w-1/2">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="end_date">Date de Fin (optionnel)</label>
                <input class="shadow appearance-none border @error('end_date') border-red-500 @enderror rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none" id="end_date" type="datetime-local" name="end_date" value="{{ old('end_date', $heatAlert->end_date ? $heatAlert->end_date->format('Y-m-d\TH:i') : '') }}">
                @error('end_date') <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                Mettre à jour
            </button>
            <a class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800" href="{{ route('admin.alerts.index') }}">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
