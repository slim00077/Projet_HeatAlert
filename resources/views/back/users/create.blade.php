@extends('layouts.back')

@section('title', 'HeatAlert - Add User')

@section('content')

<div class="page-header">
    <h1>Add User</h1>
</div>

<x-flash-messages />

<form action="{{ route('admin.users.store') }}" method="POST" class="form-card">
    @csrf
    @include('back.users._form', ['user' => null])
</form>

@endsection
