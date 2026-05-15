@extends('layouts.app')

@section('title', __('messages.film.management.list'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center">
        <h2>{{ __('messages.film.management.list') }}</h2>
        <div class="position-relative">
            <input type="text" name="search" id="search" placeholder="{{ __('messages.placeholder.search') }}">
            <div class="position-absolute start-0 top-0 p-2">
                @include('icons.search')
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="filter">{{ __('messages.filter') }}</div>
        @include('icons.filter')
    </div>
    <div class="table-responsive" id="film-table">
        @include('admin.film.table', ['films' => $films])
    </div>
</div>

@endsection