@extends('layouts.back')

@section('title', 'Liste des Quartiers')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Quartiers</h1>
        <a href="{{ route('admin.neighborhoods.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Ajouter un quartier</a>
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
                    <th class="py-3 px-6 text-left">ID</th>
                    <th class="py-3 px-6 text-left">Nom</th>
                    <th class="py-3 px-6 text-left">Description</th>
                    <th class="py-3 px-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @forelse($neighborhoods as $neighborhood)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="py-3 px-6 text-left whitespace-nowrap">{{ $neighborhood->id }}</td>
                    <td class="py-3 px-6 text-left">{{ $neighborhood->name }}</td>
                    <td class="py-3 px-6 text-left">{{ Str::limit($neighborhood->description, 50) }}</td>
                    <td class="py-3 px-6 text-center">
                        <div class="flex item-center justify-center space-x-2">
                            <a href="{{ route('admin.neighborhoods.edit', $neighborhood) }}" class="text-blue-500 hover:text-blue-700">Modifier</a>
                            <form action="{{ route('admin.neighborhoods.destroy', $neighborhood) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700">Supprimer</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="py-3 px-6 text-center">Aucun quartier trouvé.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
