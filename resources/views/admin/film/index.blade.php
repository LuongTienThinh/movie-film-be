@extends('layouts.app')

@section('title', __('messages.film.management.list'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h2 class="mb-0">{{ __('messages.film.management.list') }}</h2>
        <a class="btn admin-primary-button" href="{{ route('admin.film.create') }}">{{ __('messages.create.new') }}</a>
    </div>

    <form id="film-filter-form" action="{{ route('admin.film.management') }}" method="GET" class="film-filter-panel">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="position-relative film-search-field">
                <input type="search" name="search" id="search" value="{{ $search }}" placeholder="{{ __('messages.placeholder.search') }}" autocomplete="off">
                <span class="position-absolute start-0 top-0 p-2">@include('icons.search')</span>
            </div>
            <button class="btn admin-secondary-button" type="submit">Tìm kiếm</button>
            <button class="btn admin-ghost-button ms-auto" id="clear-film-filters" type="button">Xóa bộ lọc</button>
        </div>

        <div class="filter-group">
            <h3>{{ __('messages.genres') }}</h3>
            <div class="genre-list">
                @foreach($genres as $genre)
                    <label class="genre-item filter-item {{ in_array($genre->id, $selectedGenres) ? 'selected' : '' }}">
                        <input class="filter-checkbox" type="checkbox" name="genres[]" value="{{ $genre->id }}" {{ in_array($genre->id, $selectedGenres) ? 'checked' : '' }}>
                        <span>{{ $genre->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="filter-group">
            <h3>{{ __('messages.country') }}</h3>
            <div class="genre-list">
                @foreach($countries as $country)
                    <label class="genre-item filter-item {{ in_array($country->id, $selectedCountries) ? 'selected' : '' }}">
                        <input class="filter-checkbox" type="checkbox" name="countries[]" value="{{ $country->id }}" {{ in_array($country->id, $selectedCountries) ? 'checked' : '' }}>
                        <span>{{ $country->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="filter-group">
            <h3>{{ __('messages.film.year') }}</h3>
            <div class="genre-list">
                @foreach($years as $year)
                    <label class="genre-item filter-item {{ in_array((int) $year, $selectedYears) ? 'selected' : '' }}">
                        <input class="filter-checkbox" type="checkbox" name="years[]" value="{{ $year }}" {{ in_array((int) $year, $selectedYears) ? 'checked' : '' }}>
                        <span>{{ $year }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </form>

    <div class="table-responsive" id="film-table" aria-live="polite">
        @include('admin.film.table', ['films' => $films, 'pagination' => $pagination])
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('film-filter-form');
    const table = document.getElementById('film-table');
    const searchInput = form.querySelector('input[name="search"]');
    const clearButton = document.getElementById('clear-film-filters');
    const endpoint = form.action;
    let requestController = null;
    let searchTimer = null;

    function buildQuery(page) {
        const params = new URLSearchParams(new FormData(form));
        params.set('page', String(page));
        params.set('perPage', '10');

        if (!params.get('search')?.trim()) {
            params.delete('search');
        }

        return params;
    }

    async function loadPage(page) {
        const params = buildQuery(page);
        requestController?.abort();
        requestController = new AbortController();
        table.classList.add('is-loading');

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: requestController.signal,
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            table.innerHTML = await response.text();
            history.replaceState({}, '', `${endpoint}?${params.toString()}`);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
            }
        } finally {
            table.classList.remove('is-loading');
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadPage(1);
    });

    form.querySelectorAll('.filter-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            checkbox.closest('.filter-item').classList.toggle('selected', checkbox.checked);
            loadPage(1);
        });
    });

    searchInput.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            loadPage(1);
        }, 350);
    });

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        form.querySelectorAll('.filter-checkbox').forEach(checkbox => checkbox.checked = false);
        form.querySelectorAll('.filter-item').forEach(item => item.classList.remove('selected'));
        loadPage(1);
    });

    table.addEventListener('click', function (event) {
        const pageButton = event.target.closest('[data-page]');
        if (pageButton) {
            loadPage(Number(pageButton.dataset.page));
            return;
        }

        if (event.target.closest('#to-custom-page')) {
            const input = table.querySelector('#custom-page');
            const page = Number(input.value);
            const lastPage = Number(input.dataset.lastPage);
            if (Number.isInteger(page) && page >= 1 && page <= lastPage) {
                loadPage(page);
            }
        }
    });

    table.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.matches('#custom-page')) {
            event.preventDefault();
            event.target.closest('.btn-pgn').querySelector('#to-custom-page').click();
        }
    });
});
</script>
@endpush
