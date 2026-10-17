@extends('layouts.back')

@section('title', 'HeatAlert - New Neighborhood')

@section('content')

<div class="page-header">
    <h1>New Neighborhood</h1>
</div>

<x-flash-messages />

<form action="{{ route('admin.neighborhoods.store') }}" method="POST" class="form-card">
    @include('admin.neighborhoods._form')
</form>

@endsection
