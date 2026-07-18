@extends('layouts.app')

@section('title', __('messages.dashboard'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div>
        <h2 class="mb-1">{{ __('messages.dashboard') }}</h2>
        <p class="text-white-50 mb-0">Tổng quan dữ liệu đang được quản lý.</p>
    </div>

    <div class="admin-stat-grid">
        <a class="admin-stat" href="{{ route('admin.film.management') }}">
            <span>Phim đang hoạt động</span>
            <strong>{{ number_format($stats['films']) }}</strong>
        </a>
        <a class="admin-stat" href="{{ route('admin.genres.index') }}">
            <span>Thể loại</span>
            <strong>{{ number_format($stats['genres']) }}</strong>
        </a>
        <a class="admin-stat" href="{{ route('admin.countries.index') }}">
            <span>Quốc gia</span>
            <strong>{{ number_format($stats['countries']) }}</strong>
        </a>
        <div class="admin-stat">
            <span>Người dùng</span>
            <strong>{{ number_format($stats['users']) }}</strong>
        </div>
    </div>

    <section class="admin-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h5 mb-0">Phim cập nhật gần đây</h3>
            <a href="{{ route('admin.film.management') }}">Xem danh sách</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-start">Phim</th>
                        <th>Năm</th>
                        <th>Cập nhật</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestFilms as $film)
                        <tr>
                            <td class="text-start">
                                <a class="d-flex align-items-center gap-3 text-decoration-none" href="{{ route('admin.film.edit', $film->id) }}">
                                    <img class="poster poster-small" src="{{ $film->poster_url }}" alt="">
                                    <span>
                                        <strong class="d-block">{{ $film->name }}</strong>
                                        <small class="text-white-50">{{ $film->origin_name }}</small>
                                    </span>
                                </a>
                            </td>
                            <td>{{ $film->year ?: '-' }}</td>
                            <td>{{ optional($film->updated_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-4 text-center text-white-50">Chưa có phim.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
