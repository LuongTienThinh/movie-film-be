<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserMeta;
use App\Traits\ApiResponseTrait;
use Auth;
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
                $userTheme->meta_value = $request->theme_mode ?? 'light';
                $userTheme->save();

                return $this->successResponse($userTheme, 200, "Update theme mode sucessfully");
            }

            return $this->errorResponse(500, "User not found");
        } catch (\Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }
}
