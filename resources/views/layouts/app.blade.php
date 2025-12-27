<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('resources/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('resources/css/shine.css') }}">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <aside class="sidebar d-flex flex-column p-4 text-white h-100 gap-2 rounded-4">
        <a href="{{ route('admin.dashboard') }}">
            <img class="mw-100 py-2" src="https://animetop.id.vn/static/media/logo-dark.8fa7f755b7c9924f3948.png" alt="">
        </a>
        @foreach(config('menu') as $item)
            @if($item['type'] == 'menu-item')
                <div class="menu-item">
                    <a href="{{ isset($item['url']) ? route($item['url']) : '#' }}">
                        @if(isset($item['icon']))
                            @include($item['icon'])
                        @endif
                        <span>{{ __($item['title'] ?? '') }}</span>
                        @if(isset($item['dropdown']) && $item['dropdown'])
                            <div class="ms-auto">@include('icons.dropdown')</div>
                        @endif
                    </a>
                    
                    @if(isset($item['children']))
                        <div class="child">
                            @foreach($item['children'] as $child)
                                <a href="{{ isset($child['url']) ? route($child['url']) : '#' }}">
                                    @if(isset($child['icon']))
                                        @include($child['icon'])
                                    @endif
                                    <span>{{ __($child['title']) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @elseif($item['type'] == 'separator')
                <hr>
            @endif
        @endforeach

        <div class="mt-auto small text-white-50">
            No copyright © {{ date('Y') }}
        </div>
    </aside>

    <main class="main-content flex-grow-1">
        <div class="header px-5 py-4 d-flex justify-content-end align-items-center">
            @include('icons.user')
        </div>
        <div class="content px-5 py-4">
            @yield('content')
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="{{ asset('resources/js/shine.js') }}"></script>
    <script src="{{ asset('resources/js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>