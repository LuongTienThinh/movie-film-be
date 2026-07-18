<?php

namespace App\Http\Controllers\Admin;

use App\Models\Country;

class CountryController extends CatalogController
{
    protected string $modelClass = Country::class;

    protected string $label = 'Quốc gia';

    protected string $routePrefix = 'admin.countries';

    protected string $table = 'countries';
}
