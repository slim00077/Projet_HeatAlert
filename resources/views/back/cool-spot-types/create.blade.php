@extends('layouts.back')

@section('title', 'HeatAlert - Add Cooling Type')

@section('content')

<div class="page-header">
    <div>
        <h1>Add Cooling Type</h1>
        <p class="subtitle">Create a new category of cooling points</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.cool-spot-types.store') }}" method="POST" class="form-card">
    @include('back.cool-spot-types._form', ['coolSpotType' => null])

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.cool-spot-types.index') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>

@endsection