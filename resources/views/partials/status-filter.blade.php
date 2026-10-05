<div class="filter-tabs">
    <a href="{{ route('resident-reports.index') }}" class="btn {{ $status === '' ? 'btn-primary' : 'btn-outline' }}">All</a>
    @foreach ($statuses as $option)
        <a href="{{ route('resident-reports.index', ['status' => $option]) }}" class="btn {{ $status === $option ? 'btn-primary' : 'btn-outline' }}">{{ $option }}</a>
    @endforeach
</div>
