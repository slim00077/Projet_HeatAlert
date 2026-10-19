@extends('layouts.back')

@section('title', 'HeatAlert - Edit Advice Category')

@section('content')

<h1>Edit Advice Category</h1>

<x-flash-messages />

<form action="{{ route('admin.advice-categories.update', $adviceCategory) }}" method="POST" class="form-card">
    @csrf
    @method('PUT')
    @include('partials.advice-category-form')
</form>

@endsection
