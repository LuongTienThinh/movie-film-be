<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFilm extends Model
{
    use HasFactory;

    protected $table = 'user_film';

    protected $fillable = [
        'user_id',
        'film_id',
        'views',
        'is_follow',
    ];

    protected $casts = [
        'views' => 'integer',
        'is_follow' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function film()
    {
        return $this->belongsTo(Film::class);
    }
}
