@extends('layouts.back')

@section('title', 'HeatAlert - Edit ' . $user->name)

@section('content')

<div class="page-header">
    <div>
        <h1>{{ $user->name }}</h1>
        <p class="subtitle">{{ $user->email }}</p>
    </div>
</div>

<x-flash-messages />

<form action="{{ route('admin.users.update', $user) }}" method="POST" class="form-card">
    @csrf
    @method('PUT')
    @include('back.users._form')
</form>

@endsection
