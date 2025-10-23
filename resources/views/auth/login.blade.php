<!DOCTYPE html>
<html>
<head>
    <title>Đăng nhập</title>
</head>
<body>
    <h1>Đăng nhập</h1>

    @if (session('error'))
        <div style="color:red;">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <label>Email:</label>
        <input type="email" name="email" required><br><br>

        <label>Mật khẩu:</label>
        <input type="password" name="password" required><br><br>

        <button type="submit">Đăng nhập</button>
    </form>
</body>
</html>
