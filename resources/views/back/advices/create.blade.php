@extends('layouts.back')

@section('title', 'HeatAlert - Add Advice')

@section('content')

<h1>Add Advice</h1>

<x-flash-messages />

<form action="{{ route('admin.advices.store') }}" method="POST" enctype="multipart/form-data" class="form-card">
    @csrf
    @include('partials.advice-form')
</form>

@endsection
