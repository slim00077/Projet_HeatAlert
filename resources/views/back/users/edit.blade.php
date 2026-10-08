@extends('layouts.back')

@section('title', 'HeatAlert - Edit User')

@section('content')

<h1>Edit User</h1>

<x-flash-messages />

<form action="{{ route('admin.users.update', $user) }}" method="POST" class="form-card">
    @csrf
    @method('PUT')
    @include('back.users._form')
</form>

@endsection
