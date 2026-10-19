@extends('layouts.back')

@section('title', 'HeatAlert - Edit Neighborhood')

@section('content')

<div class="page-header">
    <h1>Edit Neighborhood</h1>
</div>

<x-flash-messages />

<form action="{{ route('admin.neighborhoods.update', $neighborhood) }}" method="POST" class="form-card">
    @method('PUT')
    @include('admin.neighborhoods._form')
</form>

@endsection
