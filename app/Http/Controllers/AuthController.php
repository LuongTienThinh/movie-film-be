<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

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

        $name = $validated['name'] ?? Str::before($validated['email'], '@');
        $name = $name !== '' ? $name : 'User';

        $user = User::create([
            'name' => $name,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
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

    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = $googleUser->getEmail();

            if (! $email) {
                return $this->errorResponse(422, 'Không lấy được email từ Google.');
            }

            $user = User::where('google_id', $googleUser->getId())->first();
            if (! $user) {
                $user = User::where('email', $email)->first();
            }

            if (! $user) {
                $name = $googleUser->getName()
                    ?: $googleUser->getNickname()
                    ?: Str::before($email, '@');

                $user = User::create([
                    'name' => $name !== '' ? $name : 'User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'google_id' => $googleUser->getId(),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                $dirty = false;
                if (! $user->google_id) {
                    $user->google_id = $googleUser->getId();
                    $dirty = true;
                }
                if (! $user->email_verified_at) {
                    $user->email_verified_at = now();
                    $dirty = true;
                }
                if ($dirty) {
                    $user->save();
                }
            }

            return $this->redirectToFrontend($user);
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed', ['exception' => $e]);

            return $this->redirectOAuthError();
        }
    }

    public function facebookRedirect()
    {
        $url = Socialite::driver('facebook')
            ->scopes(['public_profile', 'email'])
            ->fields(['id', 'name', 'email'])
            ->redirect();

        return $url;
    }

    public function facebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')
                ->fields(['id', 'name', 'email'])
                ->user();
            $email = $facebookUser->getEmail();
            $facebookId = $facebookUser->getId();

            $user = User::where('facebook_id', $facebookId)->first();
            if (! $user && $email) {
                $user = User::where('email', $email)->first();
            }

            if (! $user) {
                $name = $facebookUser->getName()
                    ?: $facebookUser->getNickname()
                    ?: Str::before($email, '@');

                $hasProviderEmail = (bool) $email;
                if (! $hasProviderEmail) {
                    $email = 'fb_' . $facebookId . '@facebook.local';
                }

                $user = User::create([
                    'name' => $name !== '' ? $name : 'User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'facebook_id' => $facebookId,
                ]);
                if ($hasProviderEmail) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            } else {
                $dirty = false;
                if (! $user->facebook_id) {
                    $user->facebook_id = $facebookId;
                    $dirty = true;
                }
                if ($email && ! $user->email_verified_at) {
                    $user->email_verified_at = now();
                    $dirty = true;
                }
                if ($email && $user->email !== $email) {
                    $user->email = $email;
                    $dirty = true;
                }
                if ($dirty) {
                    $user->save();
                }
            }

            return $this->redirectToFrontend($user);
        } catch (\Throwable $e) {
            Log::warning('Facebook OAuth callback failed', ['exception' => $e]);
            return $this->redirectOAuthError();
        }
    }

    public function exchangeOAuthCode(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:64'],
        ]);

        $cacheKey = 'oauth_exchange:' . hash('sha256', $validated['code']);
        $userId = Cache::pull($cacheKey);

        if (! $userId || ! ($user = User::find($userId))) {
            return $this->errorResponse(422, 'Mã đăng nhập không hợp lệ hoặc đã hết hạn.');
        }

        $tokenData = $this->createTokenResponse($user);

        return $this->successResponse([
            'user' => $user->only(['id', 'name', 'email', 'phone', 'gender', 'date_of_birth']),
            ...$tokenData,
        ], 200, 'Đăng nhập thành công.');
    }

    private function redirectToFrontend(User $user)
    {
        $code = Str::random(64);
        Cache::put(
            'oauth_exchange:' . hash('sha256', $code),
            $user->id,
            now()->addMinutes(2)
        );

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontendUrl . '/auth/callback?code=' . urlencode($code));
    }

    private function redirectOAuthError()
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontendUrl . '/auth/callback?error=oauth_failed');
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
