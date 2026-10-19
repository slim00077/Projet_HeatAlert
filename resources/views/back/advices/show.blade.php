@extends('layouts.back')

@section('title', 'HeatAlert - ' . $advice->title)

@section('content')

<div class="page-header">
    <div>
        <span class="chip">{{ $advice->category->name }}</span>
        <h1>{{ $advice->title }}</h1>
        <x-severity-badge :severity="$advice->risk_level" />
        @if ($advice->is_approved)
            <span class="chip">Published</span>
        @else
            <span class="chip approval-pending">Pending review</span>
        @endif
    </div>
    <div class="toolbar">
        @unless ($advice->is_approved)
            <form action="{{ route('admin.advices.approve', $advice) }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-primary">Approve and publish</button>
            </form>
        @endunless
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
