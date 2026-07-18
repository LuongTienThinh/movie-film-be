@extends('layouts.app')

@section('title', $label)

@section('content')
<div class="d-flex flex-column gap-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="mb-1">Danh sách {{ mb_strtolower($label) }}</h2>
            <p class="text-white-50 mb-0">Quản lý các mục đang được gán cho phim.</p>
        </div>
        <a class="btn admin-primary-button" href="{{ route($routePrefix . '.create') }}">Tạo mới</a>
    </div>

    <form method="GET" action="{{ route($routePrefix . '.index') }}" class="admin-search-form">
        <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('messages.placeholder.search') }}">
        <button type="submit" class="btn admin-secondary-button">Tìm kiếm</button>
        @if($search !== '')
            <a class="btn admin-ghost-button" href="{{ route($routePrefix . '.index') }}">Xóa lọc</a>
        @endif
    </form>

    <section class="admin-panel table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-start">Tên</th>
                    <th class="text-start">Slug</th>
                    <th>Số phim</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="text-start fw-semibold">{{ $item->name }}</td>
                        <td class="text-start text-white-50">{{ $item->slug }}</td>
                        <td>{{ $item->films_count }}</td>
                        <td>
                            <div class="d-flex justify-content-center gap-2">
                                <a class="icon-action" href="{{ route($routePrefix . '.edit', $item) }}" aria-label="Chỉnh sửa">@include('icons.edit')</a>
                                <form method="POST" action="{{ route($routePrefix . '.destroy', $item) }}" onsubmit="return confirm('Xác nhận xóa mục này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-action" type="submit" aria-label="Xóa">@include('icons.delete')</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-white-50">Không có dữ liệu phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{ $items->links('pagination::bootstrap-5') }}
</div>
@endsection
