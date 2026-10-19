@extends('layouts.front')

@section('title', 'HeatAlert - Add Cool Spot')

@section('content')

<section class="page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Add Cool Spot</h2>
        </div>

        <x-flash-messages />

        <form action="{{ route('cool-spots.store') }}" method="POST" class="form-card cool-spot-form">
            @include('back.cool-spots._form', ['coolSpot' => null])

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('cool-spots.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</section>

@endsection
