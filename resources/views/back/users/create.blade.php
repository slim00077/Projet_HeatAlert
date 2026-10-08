@extends('layouts.back')

@section('title', 'HeatAlert - Add User')

@section('content')

<h1>Add User</h1>

<x-flash-messages />

<form action="{{ route('admin.users.store') }}" method="POST" class="form-card">
    @csrf
    @include('back.users._form', ['user' => null])
</form>

@endsection
