<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    use ApiResponseTrait;

    public function getAllCountries()
    {
        try {
            $countries = Country::query()->get();

            $data = $countries->map(function ($genre, $index) {
                return [
                    "id" => $genre->id,
                    "name" => $genre->name,
                    "slug" => $genre->slug,
                ];
            });

            return $this->successResponse($data, 200, "Lấy danh sách quốc gia thành công");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function getCountryDetail(string $slug)
    {
        try {
            $genre = Country::query()->where("slug", "=", $slug)->first();

            $data = [
                "id" => $genre->id,
                "name" => $genre->name,
                "slug" => $genre->slug,
            ];

            return $this->successResponse($data, 200, "Lấy thông tin quốc gia thành công");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }

    }
}
