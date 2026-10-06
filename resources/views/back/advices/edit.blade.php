@extends('layouts.back')

@section('title', 'HeatAlert - Edit Advice')

@section('content')

<h1>Edit Advice</h1>

<x-flash-messages />

<form action="{{ route('admin.advices.update', $advice) }}" method="POST" class="form-card">
    @csrf
    @method('PUT')
    @include('partials.advice-form')
</form>

@endsection
