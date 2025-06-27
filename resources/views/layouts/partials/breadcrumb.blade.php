@if(isset($breadcrumbs) && count($breadcrumbs) > 0)
<nav aria-label="breadcrumb" class="pt-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}" class="text-decoration-none">
                <i class="bi bi-house-door"></i>
                Dashboard
            </a>
        </li>
        @foreach($breadcrumbs as $breadcrumb)
        @if($loop->last)
        <li class="breadcrumb-item active" aria-current="page">
            {{ $breadcrumb['title'] }}
        </li>
        @else
        <li class="breadcrumb-item">
            <a href="{{ $breadcrumb['url'] }}" class="text-decoration-none">
                {{ $breadcrumb['title'] }}
            </a>
        </li>
        @endif
        @endforeach
    </ol>
</nav>
@endif
