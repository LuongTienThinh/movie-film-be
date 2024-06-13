<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;

class GenreController extends Controller
{
    use ApiResponseTrait;

    public function getAllGenres()
    {
        try {
            $genres = Genre::query()->get();

            $data = $genres->map(function ($genre, $index) {
                return [
                    "id" => $genre->id,
                    "name" => $genre->name,
                    "slug" => $genre->slug,
                ];
            });

            return $this->successResponse($data, 200, "Lấy danh sách thể loại thành công");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }

    }

    public function getGenreDetail(string $slug)
    {
        try {
            $genre = Genre::query()->where("slug", "=", $slug)->first();

            $data = [
                "id" => $genre->id,
                "name" => $genre->name,
                "slug" => $genre->slug,
            ];

            return $this->successResponse($data, 200, "Lấy thông tin thể loại thành công");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }

    }
}
