<?php

namespace App\Http\Controllers\Admin;

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
}
