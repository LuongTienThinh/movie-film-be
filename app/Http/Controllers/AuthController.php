<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function login(AuthRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return $this->errorResponse(401, 'Thông tin đăng nhập không hợp lệ.');
        }

        $user = Auth::user();

        $tokenData = $this->createTokenResponse($user);

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return $this->successResponse([
            'user' => $userData,
            'token' => $tokenData['token'],
            'token_type' => $tokenData['token_type'],
            'expires_in' => $tokenData['expires_in'],
        ], 200, 'Đăng nhập thành công.');
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

        $tokenData = $this->createTokenResponse($user);

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return $this->successResponse([
            'user' => $userData,
            'token' => $tokenData['token'],
            'token_type' => $tokenData['token_type'],
            'expires_in' => $tokenData['expires_in'],
        ], 201, 'Đăng ký thành công.');
    }

    public function logout()
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return $this->errorResponse(401, 'Chưa đăng nhập.');
            }

            $current = $user->currentAccessToken();
            if ($current) {
                $current->delete();
            }

            return $this->successResponse([], 200, 'Đăng xuất thành công.');
        } catch (\Exception $e) {
            error_log('Lỗi đăng xuất: ' . $e->getMessage());
            return $this->errorResponse(500, 'Lỗi khi đăng xuất.');
        }
    }

    /**
     * Send password reset link to email.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return $this->successResponse([], 200, trans($status));
        }

        return $this->errorResponse(400, trans($status));
    }

    /**
     * Reset user password using token from email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse([], 200, trans($status));
        }

        return $this->errorResponse(400, trans($status));
    }

    /**
     * Create token and return consistent token payload.
     */
    private function createTokenResponse(User $user): array
    {
        $token = $user->createToken('auth-token');

        $expiresIn = null;
        $sanctumExp = config('sanctum.expiration');
        if (! empty($sanctumExp)) {
            $expiresIn = (int) $sanctumExp * 60; // minutes -> seconds
        }

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
        ];
    }
}
