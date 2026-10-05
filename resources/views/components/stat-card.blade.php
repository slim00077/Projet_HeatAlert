@props(['label', 'value', 'icon' => 'grid', 'tone' => 'blue'])

<div class="stat-card">
    <div class="stat-icon soft-{{ $tone }}">
        <x-icon :name="$icon" />
    </div>
    <div>
        <small>{{ $label }}</small>
        <div class="stat-value">{{ $value }}</div>
    </div>
</div>
