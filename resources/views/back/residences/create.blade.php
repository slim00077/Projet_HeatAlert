@extends('layouts.back')

@section('title', 'HeatAlert - Add Residence')

@section('content')

<h1>Add Residence</h1>

<x-flash-messages />

<form action="{{ route('residences.store') }}" method="POST" enctype="multipart/form-data" class="form-card">
    @csrf
    @include('partials.residence-form')
</form>

@endsection
