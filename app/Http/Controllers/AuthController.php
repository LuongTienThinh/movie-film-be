<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Auth;
use Hash;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function login(AuthRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->successResponse(["user" => $user, "token" => $token], 200, "Đăng nhập thành công.");
        } else {
            return $this->errorResponse(500, "Sai tài khoản hoặc mật khẩu.");
        }
    }

    public function register(AuthRequest $request)
    {
        $user = User::query()->where('email', $request->only('email'))->first();

        if (!$user) {
            $createUser = User::create([
                'name' => $request->validated()['email'],
                'email' => $request->validated()['email'],
                'password' => Hash::make($request->validated()['password']),
            ]);

            return $this->successResponse(["user" => $createUser], 200, "Đăng ký thành công.");
        } else {
            return $this->errorResponse(500, "Email đã tồn tại");
        }
    }

    public function logout()
    {
        try {
            Auth::user()->currentAccessToken()->delete();
            return $this->successResponse(["user" => null], 200, "Đăng xuất tài khoản thành công.");
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }
}
