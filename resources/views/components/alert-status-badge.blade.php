@props(['alert'])

<x-status-badge :status="ucfirst($alert->status())" />
