<?php

namespace App\Http\Controllers\Admin;

use App\Models\Genre;

class GenreController extends CatalogController
{
    protected string $modelClass = Genre::class;

    protected string $label = 'Thể loại';

    protected string $routePrefix = 'admin.genres';

    protected string $table = 'genres';
}
