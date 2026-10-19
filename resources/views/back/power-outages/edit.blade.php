@extends('layouts.back')

@section('title', 'Edit power outage - Admin')

@section('content')
    <div class="page-shell">
        <div class="page-header">
            <div>
                <span class="eyebrow">Power outages</span>
                <h1>Edit outage</h1>
            </div>
        </div>

        <form action="{{ route('admin.power-outages.update', $powerOutage) }}" method="POST" class="panel-form" novalidate data-outage-form>
            @csrf
            @method('PUT')
            @include('back.power-outages.partials.form', ['powerOutage' => $powerOutage, 'submitLabel' => 'Update outage'])
        </form>
    </div>
@endsection
