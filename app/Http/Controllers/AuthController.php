<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Auth;
use Hash;
use Illuminate\Auth\Events\Registered;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function login(AuthRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            $token = $user->createToken('auth-token')->plainTextToken;
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];

            return $this->successResponse(['user' => $userData, 'token' => $token], 200, 'Đăng nhập thành công.');
        }

        return $this->errorResponse(401, 'Thông tin đăng nhập không hợp lệ.');
    }

    public function register(AuthRequest $request)
    {
        $validated = $request->validated();
        
        if (User::where('email', $validated['email'])->exists()) {
            return $this->errorResponse(422, 'Email đã tồn tại.');
        }

        $user = User::create([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $token = $user->createToken('auth-token')->plainTextToken;

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    
        return $this->successResponse(['user' => $userData, 'token' => $token], 201, 'Đăng ký thành công.');
    }

    public function logout()
    {
        try {
            if (!Auth::check()) {
                return $this->errorResponse(401, 'Chưa đăng nhập.');
            }

            Auth::user()->currentAccessToken()->delete();
            return $this->successResponse([], 200, 'Đăng xuất thành công.');
        } catch (\Exception $e) {
            error_log('Lỗi đăng xuất: ' . $e->getMessage());
            return $this->errorResponse(500, 'Lỗi khi đăng xuất.');
        }
    }
}
