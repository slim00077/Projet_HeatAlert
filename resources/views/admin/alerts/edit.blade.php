@extends('layouts.back')

@section('title', 'HeatAlert - Edit Heat Alert')

@section('content')

<div class="page-header">
    <h1>Edit Heat Alert</h1>
</div>

<x-flash-messages />

<form action="{{ route('admin.alerts.update', $heatAlert) }}" method="POST" enctype="multipart/form-data" class="form-card">
    @method('PUT')
    @include('admin.alerts._form')
</form>

@endsection
