@extends('layouts.back')

@section('title', 'HeatAlert - Add Advice Category')

@section('content')

<h1>Add Advice Category</h1>

<x-flash-messages />

<form action="{{ route('admin.advice-categories.store') }}" method="POST" class="form-card">
    @csrf
    @include('partials.advice-category-form')
</form>

@endsection
