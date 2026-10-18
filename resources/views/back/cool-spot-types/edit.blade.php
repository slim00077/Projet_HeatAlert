@extends('layouts.back')

@section('title', 'HeatAlert - Edit Cooling Type')

@section('content')

<div class="page-header">
    <div>
        <h1>Edit Cooling Type</h1>
        <p class="subtitle">{{ $coolSpotType->name }}</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.cool-spot-types.update', $coolSpotType) }}" method="POST" class="form-card">
    @method('PUT')
    @include('back.cool-spot-types._form')

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="{{ route('admin.cool-spot-types.index') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>

@endsection