@php
    $page = $pagination['page'];
    $lastPage = $pagination['last_page'];
    $sibling = $pagination['sibling'] ?? 1;
    $pages = [];

    if ($lastPage <= $sibling * 2 + 4) {
        for ($i = 1; $i <= $lastPage; $i++) $pages[] = $i;
    } elseif ($page <= $sibling + 3) {
        for ($i = 1; $i <= $sibling * 2 + 3; $i++) $pages[] = $i;
        $pages[] = -2;
        $pages[] = $lastPage;
    } elseif ($page > $lastPage - $sibling - 2) {
        $pages[] = 1;
        $pages[] = -2;
        for ($i = $lastPage - ($sibling * 2 + 2); $i <= $lastPage; $i++) $pages[] = $i;
    } else {
        $pages[] = 1;
        $pages[] = -1;
        for ($i = $page - $sibling; $i <= $page + $sibling; $i++) $pages[] = $i;
        $pages[] = -2;
        $pages[] = $lastPage;
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
        @forelse($films as $film)
            <tr>
                <td class="text-start max-w-400">
                    <div class="d-flex align-items-center gap-2">
                        <img class="poster" src="{{ $film->poster_url }}" alt="">
                        <div class="d-flex flex-column">
                            <span class="name fw-semibold text-ellipsis-2">{{ $film->name }}</span>
                            <span class="origin-name text-ellipsis-2">{{ $film->origin_name }}</span>
                        </div>
                    </div>
                </td>
                <td>{{ $film->year ?: '-' }}</td>
                <td>{{ $film->status?->name ?: '-' }}</td>
                <td>{{ $film->type?->name ?: '-' }}</td>
                <td class="max-w-150">{{ $film->genres->pluck('name')->implode(', ') ?: '-' }}</td>
                <td>{{ $film->countries->pluck('name')->implode(', ') ?: '-' }}</td>
                <td>
                    <div class="d-flex justify-content-center align-items-center gap-2">
                        <a class="icon-action" href="{{ route('admin.film.edit', $film->id) }}" aria-label="Chỉnh sửa">@include('icons.edit')</a>
                        <form action="{{ route('admin.film.delete', $film->id) }}" method="POST" onsubmit="return confirm('Xác nhận xóa phim này?')">
                            @csrf
                            @method('DELETE')
                            <button class="icon-action" type="submit" aria-label="Xóa">@include('icons.delete')</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="py-5 text-center text-white-50">Không tìm thấy phim phù hợp.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="pagination d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>{{ $page }} / {{ $lastPage }} - {{ $pagination['total_films'] }} phim</span>

    <div class="list-paginate d-flex flex-wrap justify-content-center align-items-center gap-2">
        @if($pagination['is_prev'])
            <button type="button" class="btn-pgn" data-page="{{ $page - 1 }}">Trang trước</button>
        @endif

        @foreach($pages as $p)
            @if($p === -1)
                <span class="btn-pgn pointer-events-none">&hellip;</span>
            @elseif($p === -2)
                <span class="d-flex align-items-center btn-pgn">
                    <input id="custom-page" data-last-page="{{ $lastPage }}" inputmode="numeric" placeholder="..." autocomplete="off">
                    <button type="button" id="to-custom-page" aria-label="Đến trang">@include('icons.arrow-right')</button>
                </span>
            @elseif($p === $page)
                <span class="btn-pgn active pointer-events-none">{{ $p }}</span>
            @else
                <button type="button" class="btn-pgn" data-page="{{ $p }}">{{ $p }}</button>
            @endif
        @endforeach

        @if($pagination['is_next'])
            <button type="button" class="btn-pgn" data-page="{{ $page + 1 }}">Trang sau</button>
        @endif
    </div>
</div>
