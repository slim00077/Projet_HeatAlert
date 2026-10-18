@extends('layouts.back')

@section('title', 'HeatAlert - ' . $adviceCategory->name)

@section('content')

<div class="page-header">
    <div>
        <h1>{{ $adviceCategory->name }}</h1>
        <p class="subtitle">{{ $adviceCategory->description ?: 'No description provided.' }}</p>
    </div>
    <div class="toolbar">
        <a href="{{ route('admin.advice-categories.edit', $adviceCategory) }}" class="btn btn-outline">Edit</a>
        <a href="{{ route('admin.advice-categories.index') }}" class="btn btn-outline">Back</a>
    </div>
</div>

<x-flash-messages />

<div class="table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Advice</th>
                <th>Summary</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($advices as $advice)
                <tr>
                    <td><a href="{{ route('admin.advices.show', $advice) }}">{{ $advice->title }}</a></td>
                    <td class="wrap">{{ \Illuminate\Support\Str::limit($advice->summary ?? $advice->content, 120) }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.advices.edit', $advice) }}" class="icon-action is-edit" title="Edit"><x-icon name="pencil" /></a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">There is no advice in this category yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $advices->links() }}

@endsection
