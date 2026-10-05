@props(['status'])

<span class="status-badge status-{{ \Illuminate\Support\Str::slug($status) }}">{{ $status }}</span>
