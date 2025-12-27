@extends('layouts.app')

@section('title', __('messages.film.management.detail'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center">
        <h2>{{ __('messages.film.management.detail') }}</h2>
    </div>
    <div class="film-detail row">
        <div class="col-8">
            <form action="{{ route('admin.film.update', ['id' => $film->id]) }}" method="post">
                <div class="form-group row">
                    <label class="col-3 require" for="name">Tên phim</label>
                    <input class="col-9" type="text" name="name" id="name" value="{{ $film->name }}">
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="origin_name">Tên gốc</label>
                    <input class="col-9" type="text" name="origin_name" id="origin_name" value="{{ $film->origin_name }}">
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="slug">Slug</label>
                    <input class="col-9" type="text" name="slug" id="slug" value="{{ $film->slug }}" disabled>
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="status">Trạng thái</label>
                    <!-- <input class="col-9" type="text" name="status" id="status" value="{{ $film->status->name == 'completed' ? 'Hoàn thành' : 'Đang ra' }}"> -->
                    <div class="col-9 shine-select" data-mode="multiple">
                        <button class="shine-select-header">
                            <span class="selected-values" data-selected="{{ $film->status->name }}">
                                <span class="badge" data-value="{{ $film->status->name }}">
                                </span>
                                <input type="text" class="search-input" placeholder="Select an option">
                            </span>

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" fill="currentColor">
                                <path d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c-12.5-12.5-12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z" />
                            </svg>
                        </button>
                        <div class="shine-select-body">
                            <div class="shine-select-item selected" data-value="a_wonderful_serenity">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                    <path d="M288 509.3L288 387.5L193.7 464.6C219.8 487.4 252.2 503.3 288 509.3zM153.2 415.1L288 304.8L288 130.7C197.2 145.9 128 224.9 128 320C128 354.6 137.2 387.1 153.2 415.1zM446.3 464.6L352 387.5L352 509.3C387.7 503.3 420.1 487.4 446.3 464.6zM486.9 415.1C502.9 387.1 512.1 354.6 512.1 320C512.1 224.9 442.9 145.9 352.1 130.7L352.1 304.9L486.9 415.2zM64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576C178.6 576 64 461.4 64 320z" />
                                </svg>
                                <span>A wonderful serenity</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="total_ep">Tổng số tập</label>
                    <input class="col-9" type="text" name="total_ep" id="total_ep" value="{{ $film->episode_total }}">
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="time">Thời lượng</label>
                    <input class="col-9" type="text" name="time" id="time" value="{{ $film->time }}">
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="countries">Quốc gia</label>
                    <input class="col-9" type="text" name="countries" id="countries" value="{{ $film->countries->pluck('name')->implode(', ') }}">
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="year">Năm phát hành</label>
                    <input class="col-9" type="text" name="year" id="year" value="{{ $film->year }}">
                </div>
                <div class="form-group row">
                    <label class="col-3 require" for="quality">Chất lượng</label>
                    <input class="col-9" type="text" name="quality" id="quality" value="{{ $film->quality }}">
                </div>
            </form>
        </div>
        <div class="col-4">
            Poster
        </div>
    </div>
</div>

@endsection