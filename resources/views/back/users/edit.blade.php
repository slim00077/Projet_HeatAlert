@extends('layouts.back')

@section('title', 'HeatAlert - Edit ' . $user->name)

@section('content')

<div class="page-header">
    <div>
        <h1>{{ $user->name }}</h1>
        <p class="subtitle">{{ $user->email }}</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.users.update', $user) }}" method="POST" class="form-card">
    @csrf
    @method('PUT')

    <div class="form-group @error('role') has-error @enderror">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="resident" @selected(old('role', $user->role) === 'resident')>Resident - lives in a residence and reports issues</option>
            <option value="manager" @selected(old('role', $user->role) === 'manager')>Residence Manager - creates residences and adds residents</option>
            <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin - full access</option>
        </select>
        @error('role')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('neighborhood_id') has-error @enderror" id="neighborhood-field">
        <label for="neighborhood_id">Neighborhood (managers only)</label>
        <select id="neighborhood_id" name="neighborhood_id">
            <option value="">-- Select a neighborhood --</option>
            @foreach ($neighborhoods as $neighborhood)
                <option value="{{ $neighborhood->id }}" @selected(old('neighborhood_id', $user->neighborhood_id) == $neighborhood->id)>{{ $neighborhood->name }}</option>
            @endforeach
        </select>
        <p class="field-hint">A manager can only create residences in this neighborhood.</p>
        @error('neighborhood_id')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>

<script>
    (function () {
        const role = document.getElementById('role');
        const field = document.getElementById('neighborhood-field');
        const sync = () => { field.style.display = role.value === 'manager' ? '' : 'none'; };
        role.addEventListener('change', sync);
        sync();
    })();
</script>

@endsection
