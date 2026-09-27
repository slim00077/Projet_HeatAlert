@extends('layouts.back')

@section('title', 'HeatAlert - Dashboard')

@section('content')

<h1>Dashboard</h1>

<p>
    Welcome to the HeatAlert administration area.
</p>

<div class="dashboard-grid">

    <div class="dashboard-card">
        <h3>Heat Alerts</h3>
        <p>Manage heat alerts.</p>
    </div>

    <div class="dashboard-card">
        <h3>Power Outages</h3>
        <p>Manage power outages.</p>
    </div>

    <div class="dashboard-card">
        <h3>Cooling Points</h3>
        <p>Manage cooling points.</p>
    </div>

    <div class="dashboard-card">
        <h3>Advice</h3>
        <p>Manage advice.</p>
    </div>

</div>

@endsection