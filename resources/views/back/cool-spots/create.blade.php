@extends('layouts.back')

@section('title', 'HeatAlert - Add Cooling Point')

@section('content')

<div class="page-header">
    <div>
        <h1>Add Cooling Point</h1>
        <p class="subtitle">Add a place where residents can cool down</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.cool-spots.store') }}" method="POST" class="form-card cool-spot-form">
    @include('back.cool-spots._form', ['coolSpot' => null])

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.cool-spots.index') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>

@endsection