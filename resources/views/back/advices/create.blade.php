@extends('layouts.back')

@section('title', 'HeatAlert - Add Advice')

@section('content')

<h1>Add Advice</h1>
<p class="subtitle">New advice is saved as pending. Review its quality report before you decide whether to publish it.</p>

<x-flash-messages />

<form action="{{ route('admin.advices.store') }}" method="POST" enctype="multipart/form-data" class="form-card">
    @csrf
    @include('partials.advice-form')
</form>

@endsection
