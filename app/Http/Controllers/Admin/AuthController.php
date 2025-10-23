<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AuthRequest;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginAdmin() {
        return view('auth.login');
    }

    public function loginAdmin(AuthRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            if ($user->role != 'admin') {
                Auth::logout();
                return redirect()->route('login')->with('error', 'Thông tin đăng nhập không hợp lệ.');
            }

            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('login')->with('error', 'Thông tin đăng nhập không hợp lệ.');
    }

}
