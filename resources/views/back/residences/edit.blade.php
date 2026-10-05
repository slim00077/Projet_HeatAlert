@extends('layouts.back')

@section('title', 'HeatAlert - Edit Residence')

@section('content')

<h1>Edit Residence</h1>

<x-flash-messages />

<form action="{{ route('residences.update', $residence) }}" method="POST" enctype="multipart/form-data" class="form-card">
    @csrf
    @method('PUT')
    @include('partials.residence-form')
</form>

@endsection
