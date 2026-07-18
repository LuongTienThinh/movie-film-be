<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserMeta;
use App\Traits\ApiResponseTrait;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Exception;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function getThemeMode(Request $request)
    {
        try {
            $user = Auth::user();
    
            if ($user) {
                $userTheme = UserMeta::query()->where('user_id', '=', $user->id)->where('meta_key', '=', 'theme_mode')->first();
    
                $userTheme ??= new UserMeta([
                    'user_id' => $user->id,
                    'meta_key' => 'theme_mode',
                    'meta_value' => 'light',
                ]);

                return $this->successResponse($userTheme, 200, "Update theme mode sucessfully");
            }

            return $this->errorResponse(500, "User not found");
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateThemeMode(Request $request)
    {
        $validated = $request->validate([
            'theme_mode' => ['required', 'in:light,dark'],
        ]);

        try {
            $user = Auth::user();
    
            if ($user) {
                $userTheme = UserMeta::query()->where('user_id', '=', $user->id)->where('meta_key', '=', 'theme_mode')->first();
    
                if (!$userTheme) {
                    $userTheme = new UserMeta();
                    $userTheme->user_id = $user->id;
                    $userTheme->meta_key = 'theme_mode';
                }
                $userTheme->meta_value = $validated['theme_mode'];
                $userTheme->save();

                return $this->successResponse($userTheme, 200, "Update theme mode sucessfully");
            }

            return $this->errorResponse(500, "User not found");
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateEmail(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'current_password' => ['required', 'string'],
        ]);

        try {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse(422, 'Mật khẩu hiện tại không đúng.');
            }

            $user->email = $validated['email'];
            $user->email_verified_at = null;
            $user->save();

            return $this->successResponse(
                $user->only(['id', 'name', 'email', 'phone', 'gender', 'date_of_birth']),
                200,
                'Cập nhật email thành công.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updatePhone(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s.-]{7,19}$/'],
        ]);

        try {
            $user = $request->user();
            $user->phone = $validated['phone'];
            $user->save();

            return $this->successResponse(
                $user->only(['id', 'name', 'email', 'phone', 'gender', 'date_of_birth']),
                200,
                'Cập nhật số điện thoại thành công.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        try {
            $user = $request->user();

            if (! Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse(422, 'Mật khẩu hiện tại không đúng.');
            }

            $user->password = Hash::make($validated['password']);
            $user->save();

            return $this->successResponse([], 200, 'Đổi mật khẩu thành công.');
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }
}
