<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserMeta;
use App\Traits\ApiResponseTrait;
use Auth;
use Illuminate\Support\Facades\Hash;
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
    
                if (!$userTheme) {
                    $userTheme = new UserMeta();
                    $userTheme->user_id = $user->id;
                    $userTheme->meta_key = 'theme_mode';
                    $userTheme->meta_value = 'light';
                    $userTheme->save();
                }

                return $this->successResponse($userTheme, 200, "Update theme mode sucessfully");
            }

            return $this->errorResponse(500, "User not found");
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateThemeMode(Request $request)
    {
        try {
            $user = Auth::user();
    
            if ($user) {
                $userTheme = UserMeta::query()->where('user_id', '=', $user->id)->where('meta_key', '=', 'theme_mode')->first();
    
                if (!$userTheme) {
                    $userTheme = new UserMeta();
                    $userTheme->user_id = $user->id;
                    $userTheme->meta_key = 'theme_mode';
                }
                $userTheme->meta_value = $request->theme_mode ?: 'light';
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
        try {
            $user = Auth::user();
            if (! $user) {
                return $this->errorResponse(401, 'Chưa đăng nhập.');
            }

            $request->validate([
                'email' => 'required|email|unique:users,email,' . $user->id,
            ]);

            $user->email = $request->email;
            $user->save();

            return $this->successResponse($user, 200, 'Cập nhật email thành công.');
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updatePhone(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return $this->errorResponse(401, 'Chưa đăng nhập.');
            }

            $request->validate([
                'phone' => 'required|string|max:20',
            ]);

            $user->phone = $request->phone;
            $user->save();

            return $this->successResponse($user, 200, 'Cập nhật số điện thoại thành công.');
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return $this->errorResponse(401, 'Chưa đăng nhập.');
            }

            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|confirmed|min:8',
            ]);

            if (! Hash::check($request->current_password, $user->password)) {
                return $this->errorResponse(422, 'Mật khẩu hiện tại không đúng.');
            }

            $user->password = Hash::make($request->password);
            $user->save();

            return $this->successResponse([], 200, 'Đổi mật khẩu thành công.');
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }
}
