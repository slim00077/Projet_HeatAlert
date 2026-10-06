@extends('layouts.back')

@section('title', 'Liste des Alertes Chaleur')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Alertes Chaleur</h1>
        <a href="{{ route('admin.alerts.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Ajouter une alerte</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow-md rounded my-6">
        <table class="min-w-full table-auto">
            <thead>
                <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Titre</th>
                    <th class="py-3 px-6 text-left">Quartier</th>
                    <th class="py-3 px-6 text-left">Sévérité</th>
                    <th class="py-3 px-6 text-left">Début</th>
                    <th class="py-3 px-6 text-left">Fin</th>
                    <th class="py-3 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @forelse($alerts as $alert)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6 text-left whitespace-nowrap font-medium">{{ $alert->title }}</td>
                    <td class="py-3 px-6 text-left">{{ $alert->neighborhood->name ?? 'N/A' }}</td>
                    <td class="py-3 px-6 text-left">
                        @if($alert->severity_level == 'high')
                            <span class="bg-red-200 text-red-600 py-1 px-3 rounded-full text-xs">Élevée</span>
                        @elseif($alert->severity_level == 'medium')
                            <span class="bg-yellow-200 text-yellow-600 py-1 px-3 rounded-full text-xs">Moyenne</span>
                        @else
                            <span class="bg-green-200 text-green-600 py-1 px-3 rounded-full text-xs">Faible</span>
                        @endif
                    </td>
                    <td class="py-3 px-6 text-left">{{ $alert->start_date->format('d/m/Y H:i') }}</td>
                    <td class="py-3 px-6 text-left">{{ $alert->end_date ? $alert->end_date->format('d/m/Y H:i') : '-' }}</td>
                    <td class="py-3 px-6 text-center">
                        <div class="flex item-center justify-center space-x-2">
                            <a href="{{ route('admin.alerts.edit', $alert) }}" class="text-blue-500 hover:text-blue-700">Modifier</a>
                            <form action="{{ route('admin.alerts.destroy', $alert) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-3 px-6 text-center">Aucune alerte chaleur trouvée.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
