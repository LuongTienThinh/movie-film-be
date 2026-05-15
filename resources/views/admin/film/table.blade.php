@php
    $page = $pagination['page'];
    $lastPage = $pagination['last_page'];
    $sibling = $pagination['sibling'] ?? 1;
    $firstPage = 1;

    $pages = [];

    if ($lastPage <= $sibling * 2 + 4) {
        for ($i = $firstPage; $i <= $lastPage; $i++) $pages[] = $i;
    } else {
        if ($page <= $sibling + 3) {
            for ($i = $firstPage; $i <= $sibling * 2 + 3; $i++) $pages[] = $i;
            $pages[] = -2;
            $pages[] = $lastPage;
        } elseif ($page > $lastPage - $sibling - 2) {
            $pages[] = $firstPage;
            $pages[] = -2;
            for ($i = $lastPage - ($sibling * 2 + 2); $i <= $lastPage; $i++) $pages[] = $i;
        } else {
            $pages[] = $firstPage;
            $pages[] = -1;
            for ($i = $page - $sibling; $i <= $page + $sibling; $i++) $pages[] = $i;
            $pages[] = -2;
            $pages[] = $lastPage;
        }
    }
@endphp

<table class="table bg-transparent align-middle">
    <thead class="table-light">
        <tr>
            <th class="text-start">Tên phim</th>
            <th>Năm</th>
            <th>Tình trạng</th>
            <th>Định dạng</th>
            <th>Thể loại</th>
            <th>Quốc gia</th>
            <th width="150">Hành động</th>
        </tr>
    </thead>
    <tbody>
        @foreach($films as $index => $film)
            <tr>
                <td class="text-start max-w-400">
                    <div class="d-flex align-items-center gap-2">
                        <img class="poster" src="{{ $film->poster_url }}" alt="{{ $film->slug }}">
                        <div class="d-flex flex-column">
                            <span class="name fw-semibold text-ellipsis-2">{{ $film->name }}</span>
                            <span class="origin-name text-ellipsis-2">{{ $film->origin_name }}</span>
                        </div>
                    </div>
                </td>
                <td>{{ $film->year ?: '-' }}</td>
                <td>{{ $film->status->name == 'completed' ? 'Hoàn thành' : 'Đang ra' }}</td>
                <td>{{ $film->type->name == 'series' ? 'Phim bộ' : 'Phim lẻ' }}</td>
                <td class="max-w-150">{{ $film->genres->pluck('name')->implode(', ') ?: '-' }}</td>
                <td>{{ $film->countries->pluck('name')->implode(', ') ?: '-' }}</td>
                <td>
                    <div class="d-flex justify-content-center align-items-center gap-1">
                        <a href="{{ route('admin.film.edit', $film->id) }}">@include('icons.edit')</a>
                        <button class="bg-transparent" id="delete" onclick="return confirm('Xác nhận xóa phim này?')">@include('icons.delete')</button>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
<div class="pagination d-flex justify-content-between align-items-center gap-2">
    <span>{{ $pagination['page'] }} / {{ $pagination['last_page'] }} - {{ $pagination['total_films'] }}</span>

    <div class="list-paginate d-flex justify-content-center align-items-center gap-2">
        {{-- Nút prev --}}
        @if($pagination['is_prev'])
            <div class="btn-pgn" data-page="{{ $page - 1 }}">Trang trước</div>
        @endif

        {{-- Các trang --}}
        @foreach($pages as $p)
            @if($p == -1)
                <div class="btn-pgn">…</div>
            @elseif($p == -2)
                <div class="d-flex align-items-center btn-pgn">
                    <input id="custom-page" placeholder="..." autocomplete="off">
                    <span id="to-custom-page">@include('icons.arrow-right')</span>
                </div>
            @elseif($p == $page)
                <div class="btn-pgn active pointer-events-none">{{ $p }}</div>
            @else
                <div class="btn-pgn" data-page="{{ $p }}">{{ $p }}</div>
            @endif
        @endforeach

        {{-- Nút next --}}
        @if($pagination['is_next'])
            <div class="btn-pgn" data-page="{{ $page + 1 }}">Trang sau</div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    let currentPage = {{ $pagination['page'] }};
    let perPage = {{ $pagination['perPage'] }};

    function loadPage(page) {
        fetch(`{{ route('admin.film.management') }}?page=${page}&perPage=${perPage}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.text())
            .then(html => {
                $('#film-table').html(html);
                currentPage = page;
                window.scroll({ top: 0, behavior: 'smooth' });
            });
    }

    $(document).on('click', '.btn-pgn', function () {
        const page = $(this).data('page');
        if (page) loadPage(page);
    });

    $(document).on('click', '#to-custom-page', function () {
        loadPage($('#custom-page').val() || currentPage);
    });

    $(document).on('keydown', '#custom-page', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();

            const page = parseInt($(this).val());
            if (!isNaN(page) && page >= 1 && page <= {{ $pagination['last_page'] }}) {
                loadPage(page);
            }
        }
    });

</script>
@endpush