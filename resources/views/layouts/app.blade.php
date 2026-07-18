<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('messages.dashboard'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('resources/css/app.css') }}?v={{ config('assets.version') }}">
    <link rel="stylesheet" href="{{ asset('resources/css/shine.css') }}?v={{ config('assets.version') }}">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <aside class="sidebar d-flex flex-column p-4 text-white h-100 gap-2 rounded-4">
        <a href="{{ route('admin.dashboard') }}" aria-label="Dashboard">
            <img class="mw-100 py-2" src="https://animetop.io.vn/static/media/logo-dark.8fa7f755b7c9924f3948.png" alt="AnimeTop">
        </a>

        @foreach(config('menu') as $item)
            @if($item['type'] === 'menu-item')
                @php
                    $isActive = collect($item['active'] ?? [])->contains(fn ($pattern) => request()->routeIs($pattern));
                    $itemUrl = isset($item['url']) ? route($item['url']) : '#';
                @endphp
                <div class="menu-item {{ $isActive ? 'active' : '' }}">
                    <a href="{{ $itemUrl }}">
                        @isset($item['icon'])
                            @include($item['icon'])
                        @endisset
                        <span>{{ __($item['title'] ?? '') }}</span>
                        @if(!empty($item['dropdown']))
                            <span class="ms-auto">@include('icons.dropdown')</span>
                        @endif
                    </a>

                    @if(!empty($item['children']))
                        <div class="child">
                            @foreach($item['children'] as $child)
                                <a class="{{ request()->routeIs($child['url']) ? 'active' : '' }}" href="{{ route($child['url']) }}">
                                    @isset($child['icon'])
                                        @include($child['icon'])
                                    @endisset
                                    <span>{{ __($child['title']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <hr>
            @endif
        @endforeach

        <div class="mt-auto small text-white-50">
            &copy; {{ date('Y') }} AnimeTop Admin
        </div>
    </aside>

    <main class="main-content flex-grow-1">
        <div class="header px-5 py-3 d-flex justify-content-end align-items-center gap-3">
            <span>{{ auth()->user()->name }}</span>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light">Đăng xuất</button>
            </form>
        </div>
        <div class="content px-5 py-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @yield('content')
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
        @php
            $js = trans('messages.js');
            $js = is_array($js) ? $js : [];
            $merged = array_merge($js, ['placeholder_search' => trans('messages.placeholder.search')]);
        @endphp
        window.i18n = {!! json_encode($merged, JSON_UNESCAPED_UNICODE) !!};
    </script>
    <script src="{{ asset('resources/js/shine.js') }}?v={{ config('assets.version') }}"></script>
    <script src="{{ asset('resources/js/app.js') }}?v={{ config('assets.version') }}"></script>
    <script src="{{ asset('resources/js/film-validate.js') }}?v={{ config('assets.version') }}"></script>
    @stack('scripts')
</body>
</html>
