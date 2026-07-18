<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('resources/css/app.css') }}?v={{ config('assets.version') }}">
</head>
<body class="admin-login-page">
    <main class="admin-login-shell">
        <section class="admin-login-panel" aria-labelledby="login-title">
            <div class="mb-4">
                <p class="admin-login-kicker">AnimeTop Admin</p>
                <h1 id="login-title">Đăng nhập quản trị</h1>
                <p class="text-white-50 mb-0">Sử dụng tài khoản có quyền admin.</p>
            </div>

            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" class="d-grid gap-3">
                @csrf
                <div>
                    <label class="form-label" for="email">Email</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="form-label" for="password">Mật khẩu</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Ghi nhớ đăng nhập</span>
                </label>

                <button type="submit" class="btn admin-primary-button">Đăng nhập</button>
            </form>
        </section>
    </main>
</body>
</html>
