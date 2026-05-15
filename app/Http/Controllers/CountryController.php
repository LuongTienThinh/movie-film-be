<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;
use App\Http\Requests\CountryRequest;

class CountryController extends Controller
{
    use ApiResponseTrait;

    public function createCountry(CountryRequest $request) {
        try {
            $validated = $request->validated();

            Country::create([
                ...$validated,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return $this->successResponse($data, 201, "Country created successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateCountry(CountryRequest $request, $countryId) {
        try {
            $country = Country::findOrFail($countryId);

            $country->update($request->validated());

            return $this->successResponse($data, 201, "Country udpated successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function deleteCountry($countryId) {
        try {
            $country = Country::findOrFail($countryId);

            $country->delete();

            return $this->successResponse($data, 201, "Country deleted successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

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
