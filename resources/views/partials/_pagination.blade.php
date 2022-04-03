
    <div class="card-footer pb-0">
        <div class="float-left pb-2">
            Showing {{ number_format($page->firstItem()) }} to {{ number_format($page->lastItem()) }} of {{ number_format($page->total()) }} {{ isset($name) ? $name : 'entries' }}
            <br />
            <div class="dropdown">
                <a class="dropdown-toggle" data-toggle="dropdown" href="{{ request()->fullUrlWithQuery(['per_page' => $page->perPage() ]) }}">
                    {{ $page->perPage() }} per page
                </a>
                <div class="dropdown-menu">
                    @foreach ([10, 20, 50, 100] as $number)
                        <a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page' => $number]) }}">{{ $number }} per page</a>
                    @endforeach
                </div>
            </div>
        </div>
        @if ($page->total() > $page->count())
            <div class="ml-auto float-right">
                {{ $page->links() }}
            </div>
        @endif
    </div>
