@extends('layouts.app')

@section('title', ($item ? 'Chỉnh sửa ' : 'Tạo ') . mb_strtolower($label))

@section('content')
<div class="admin-form-shell">
    <div class="mb-4">
        <a class="text-white-50" href="{{ route($routePrefix . '.index') }}">&larr; Quay lại danh sách</a>
        <h2 class="mt-3 mb-1">{{ $item ? 'Chỉnh sửa' : 'Tạo mới' }} {{ mb_strtolower($label) }}</h2>
        <p class="text-white-50 mb-0">Slug sẽ được tạo từ tên nếu để trống.</p>
    </div>

    <form action="{{ $formAction }}" method="POST" class="admin-panel d-grid gap-3">
        @csrf
        @if($formMethod !== 'POST')
            @method($formMethod)
        @endif

        <div>
            <label class="form-label" for="name">Tên</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $item?->name) }}" required autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="form-label" for="slug">Slug</label>
            <input class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $item?->slug) }}">
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex justify-content-end gap-2 mt-2">
            <a class="btn admin-ghost-button" href="{{ route($routePrefix . '.index') }}">Hủy</a>
            <button class="btn admin-primary-button" type="submit">{{ $item ? 'Lưu thay đổi' : 'Tạo mới' }}</button>
        </div>
    </form>
</div>
@endsection
