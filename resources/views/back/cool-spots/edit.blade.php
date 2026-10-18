@extends('layouts.back')

@section('title', 'HeatAlert - Edit Cooling Point')

@section('content')

<div class="page-header">
    <div>
        <h1>Edit Cooling Point</h1>
        <p class="subtitle">{{ $coolSpot->name }}</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.cool-spots.update', $coolSpot) }}" method="POST" class="form-card">
    @method('PUT')
    @include('back.cool-spots._form')

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="{{ route('admin.cool-spots.index') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>

@endsection