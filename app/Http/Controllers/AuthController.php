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

    public function googleRedirect(Request $request)
    {
        if ($request->has('code')) {
            return $this->googleCallback($request);
        }

        $state = $this->makeRedirectState($request->query('redirect_uri'));

        $url = Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return $this->successResponse([
            'url' => $url,
        ], 200, 'Get Google login url success.');
    }

    public function googleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $email = $googleUser->getEmail();

            if (! $email) {
                return $this->errorResponse(422, 'Không lấy được email từ Google.');
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                $name = $googleUser->getName()
                    ?: $googleUser->getNickname()
                    ?: Str::before($email, '@');

                $user = User::create([
                    'name' => $name !== '' ? $name : 'User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);
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

            $tokenData = $this->createTokenResponse($user);
            $redirectUri = $this->readRedirectState($request->query('state'));

            return $this->redirectToFrontend($user, $tokenData, $redirectUri);
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function facebookRedirect(Request $request)
    {
        if ($request->has('code')) {
            return $this->facebookCallback($request);
        }

        if ($request->has('error') || $request->has('error_code')) {
            $message = $request->query('error_message')
                ?: $request->query('error_description')
                ?: $request->query('error')
                ?: 'Facebook login error.';

            return $this->errorResponse(422, $message);
        }

        $state = $this->makeRedirectState(request()->query('redirect_uri'));
        $withEmail = filter_var(request()->query('email_scope'), FILTER_VALIDATE_BOOLEAN);

        $scopes = ['public_profile'];
        $fields = ['id', 'name'];
        if ($withEmail) {
            $scopes[] = 'email';
            $fields[] = 'email';
        }

        $url = Socialite::driver('facebook')
            ->stateless()
            ->setScopes($scopes)
            ->with([
                'state' => $state,
                'fields' => implode(',', $fields),
            ])
            ->redirect()
            ->getTargetUrl();

        return $this->successResponse([
            'url' => $url,
        ], 200, 'Get Facebook login url success.');
    }

    public function facebookCallback(Request $request)
    {
        try {
            $facebookUser = Socialite::driver('facebook')
                ->stateless()
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

                if (! $email) {
                    $email = 'fb_' . $facebookId . '@facebook.local';
                }

                $user = User::create([
                    'name' => $name !== '' ? $name : 'User',
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'facebook_id' => $facebookId,
                    'email_verified_at' => $email ? now() : null,
                ]);
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

            $tokenData = $this->createTokenResponse($user);
            $redirectUri = $this->readRedirectState($request->query('state'));

            return $this->redirectToFrontend($user, $tokenData, $redirectUri);
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    private function redirectToFrontend(User $user, array $tokenData, ?string $redirectUri = null)
    {
        $frontendUrl = $redirectUri ?: env('FRONTEND_URL', config('app.url'));

        $query = http_build_query([
            'token' => $tokenData['token'],
            'token_type' => $tokenData['token_type'],
            'expires_in' => $tokenData['expires_in'],
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return redirect()->away(rtrim($frontendUrl, '/') . '/auth/callback?' . $query);
    }

    private function makeRedirectState(?string $redirectUri): ?string
    {
        if (! $redirectUri) {
            return null;
        }

        $payload = json_encode([
            'redirect_uri' => $redirectUri,
            'ts' => time(),
        ]);

        $sig = hash_hmac('sha256', $payload, $this->getAppKey());

        return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
    }

    private function readRedirectState(?string $state): ?string
    {
        if (! $state) {
            return null;
        }

        $decoded = base64_decode(strtr($state, '-_', '+/'));
        if (! $decoded || ! str_contains($decoded, '|')) {
            return null;
        }

        [$payload, $sig] = explode('|', $decoded, 2);
        $expected = hash_hmac('sha256', $payload, $this->getAppKey());
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? ($data['redirect_uri'] ?? null) : null;
    }

    private function getAppKey(): string
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
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
