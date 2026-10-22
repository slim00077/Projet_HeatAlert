@extends('layouts.front')

@section('title', 'HeatAlert - Ajouter une alerte')

@section('content')

<section class="fx-page page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Ajouter une Alerte Chaleur</h2>
        </div>

        <x-flash-messages />

        <form action="{{ route('front.alerts.store') }}" method="POST" enctype="multipart/form-data" class="form-card">
            @csrf

            <div class="form-group @error('neighborhood_id') has-error @enderror">
                <label for="neighborhood_id">Quartier</label>
                <select id="neighborhood_id" name="neighborhood_id" required>
                    <option value="">Sélectionnez un quartier</option>
                    @foreach ($neighborhoods as $neighborhood)
                        <option value="{{ $neighborhood->id }}" @selected(old('neighborhood_id') == $neighborhood->id)>{{ $neighborhood->name }}</option>
                    @endforeach
                </select>
                @error('neighborhood_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('title') has-error @enderror">
                <label for="title">Titre</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="255" required>
                @error('title')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('residence_name') has-error @enderror">
                <label for="residence_name">Nom de la résidence <span class="field-hint">(optionnel)</span></label>
                <input type="text" id="residence_name" name="residence_name" value="{{ old('residence_name') }}" maxlength="255">
                @error('residence_name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('description') has-error @enderror">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4">{{ old('description') }}</textarea>
                @error('description')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('image') has-error @enderror">
                <label for="image">Image <span class="field-hint">(optionnel)</span></label>
                <input type="file" id="image" name="image" accept="image/*">
                @error('image')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('severity_level') has-error @enderror">
                <label for="severity_level">Sévérité</label>
                <select id="severity_level" name="severity_level" required>
                    <option value="low" @selected(old('severity_level') == 'low')>Faible</option>
                    <option value="medium" @selected(old('severity_level') == 'medium')>Moyenne</option>
                    <option value="high" @selected(old('severity_level') == 'high')>Élevée</option>
                </select>
                @error('severity_level')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('start_date') has-error @enderror">
                <label for="start_date">Date de Début</label>
                <input type="datetime-local" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-group @error('end_date') has-error @enderror">
                <label for="end_date">Date de Fin <span class="field-hint">(optionnel)</span></label>
                <input type="datetime-local" id="end_date" name="end_date" value="{{ old('end_date') }}">
                @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('front.alerts.index') }}" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </div>
</section>

@endsection
