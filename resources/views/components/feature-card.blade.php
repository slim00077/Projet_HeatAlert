@props(['title', 'icon', 'tone' => 'coral', 'href' => '#'])

<a href="{{ $href }}" class="feature-card">
    <div class="feature-icon tone-{{ $tone }}">
        <x-icon :name="$icon" />
    </div>
    <div>
        <h3>{{ $title }}</h3>
        <p>{{ $slot }}</p>
    </div>
</a>
