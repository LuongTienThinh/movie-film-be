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
        'is_view',
        'is_follow',
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
