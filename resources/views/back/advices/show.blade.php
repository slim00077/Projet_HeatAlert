@extends('layouts.back')

@section('title', 'HeatAlert - ' . $advice->title)

@section('content')

<div class="page-header">
    <div>
        <span class="chip">{{ $advice->category->name }}</span>
        <h1>{{ $advice->title }}</h1>
    </div>
    <div class="toolbar">
        <a href="{{ route('admin.advices.edit', $advice) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('admin.advices.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

<article class="form-card advice-content">
    @if ($advice->summary)
        <p><strong>{{ $advice->summary }}</strong></p>
    @endif
    <div>{{ $advice->content }}</div>
</article>

@endsection
