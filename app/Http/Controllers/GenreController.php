<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\Request;
use App\Http\Requests\GenreRequest;

class GenreController extends Controller
{
    use ApiResponseTrait;

    public function createGenre(GenreRequest $request) {
        try {
            $validated = $request->validated();

            Genre::create([
                ...$validated,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return $this->successResponse($data, 201, "Genre created successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function updateGenre(GenreRequest $request, $genreId) {
        try {
            $genre = Genre::findOrFail($genreId);

            $genre->update($request->validated());

            return $this->successResponse($data, 201, "Genre udpated successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

    public function deleteGenre($genreId) {
        try {
            $genre = Genre::findOrFail($genreId);

            $genre->delete();

            return $this->successResponse($data, 201, "Genre deleted successfully!");
        } catch (Exception $e) {
            return $this->errorResponse(500, $e->getMessage());
        }
    }

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
