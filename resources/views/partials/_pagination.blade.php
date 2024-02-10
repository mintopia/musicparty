@php
    $page->useBootstrap();
    $page->onEachSide(3);
@endphp
<div class="card-footer d-flex align-items-center">
    <p class="m-0 mb-1 text-muted">
        Showing <span>{{ number_format($page->firstItem()) }}</span> to
        <span>{{ number_format($page->lastItem()) }}</span>
        of <span>{{ number_format($page->total()) }}</span> {{ isset($name) ? $name : 'entries' }}
    </p>

    @if ($page->hasPages())
        <div class="m-0 ms-auto">
            {{ $page->links() }}
        </div>
    @endif
</div>
