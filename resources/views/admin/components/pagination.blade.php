@if(isset($paginator) && method_exists($paginator, 'lastPage') && $paginator->lastPage() > 1)
    <div class="admin-pagination">
        <div class="pagination-info">
            Showing {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} records
        </div>

        <div class="pagination-actions">
            @if($paginator->onFirstPage())
                <span class="pagination-btn disabled">&lt;</span>
            @else
                <a class="pagination-btn" href="{{ $paginator->previousPageUrl() }}">&lt;</a>
            @endif

            @php
                $start = max($paginator->currentPage() - 2, 1);
                $end = min($start + 4, $paginator->lastPage());
                $start = max($end - 4, 1);
            @endphp

            @if($start > 1)
                <a class="pagination-btn" href="{{ $paginator->url(1) }}">1</a>
                @if($start > 2)
                    <span class="pagination-dots">...</span>
                @endif
            @endif

            @for($page = $start; $page <= $end; $page++)
                @if($page === $paginator->currentPage())
                    <span class="pagination-btn active">{{ $page }}</span>
                @else
                    <a class="pagination-btn" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
            @endfor

            @if($end < $paginator->lastPage())
                @if($end < $paginator->lastPage() - 1)
                    <span class="pagination-dots">...</span>
                @endif
                <a class="pagination-btn" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a>
            @endif

            @if($paginator->hasMorePages())
                <a class="pagination-btn" href="{{ $paginator->nextPageUrl() }}">&gt;</a>
            @else
                <span class="pagination-btn disabled">&gt;</span>
            @endif
        </div>
    </div>
@endif
