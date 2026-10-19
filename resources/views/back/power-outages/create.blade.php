@extends('layouts.back')

@section('title', 'Create power outage - Admin')

@section('content')
    <div class="page-shell">
        <div class="page-header">
            <div>
                <span class="eyebrow">Power outages</span>
                <h1>Create outage</h1>
            </div>
        </div>

        <form action="{{ route('admin.power-outages.store') }}" method="POST" class="panel-form">
            @include('back.power-outages.partials.form', ['powerOutage' => $powerOutage, 'submitLabel' => 'Create outage'])
        </form>
    </div>
@endsection
