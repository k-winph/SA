@if ($paginator->hasPages())
    @php
        $rawPrevious = __('pagination.previous');
        $rawNext = __('pagination.next');

        $previousLabel = trim(preg_replace('/^[«‹\s]+/u', '', strip_tags($rawPrevious)));
        $nextLabel = trim(preg_replace('/[»›\s]+$/u', '', strip_tags($rawNext)));

        $previousLabel = $previousLabel !== '' ? $previousLabel : $rawPrevious;
        $nextLabel = $nextLabel !== '' ? $nextLabel : $rawNext;
    @endphp

    <nav class="pagination-slim" role="navigation" aria-label="Pagination Navigation">

        <p class="pagination-summary small text-muted mb-0">
            {!! __('Showing') !!}
            <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
            {!! __('to') !!}
            <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
            {!! __('of') !!}
            <span class="fw-semibold">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        <div class="pagination-pages">
            @if ($paginator->onFirstPage())
                <span class="page-link disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                    <span class="page-icon" aria-hidden="true">&lsaquo;</span>
                </span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
                    <span class="page-icon" aria-hidden="true">&lsaquo;</span>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-link disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-link active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
                    <span class="page-icon" aria-hidden="true">&rsaquo;</span>
                </a>
            @else
                <span class="page-link disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                    <span class="page-icon" aria-hidden="true">&rsaquo;</span>
                </span>
            @endif
        </div>
    </nav>
@endif
