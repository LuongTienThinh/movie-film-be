<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Episode extends Model
{
    use HasFactory;

    protected $fillable = [
        'film_id',
        'title',
        'name',
        'slug',
        'link',
        'created_at',
        'updated_at',
    ];
}
