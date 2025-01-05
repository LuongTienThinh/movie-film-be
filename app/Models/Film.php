<?php

namespace App\Models;

use App\Traits\FullTextSearchTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Film extends Model
{
    use HasFactory;
    use FullTextSearchTrait;

    protected $hidden = [
        'type_id',
        'status_id',
    ];

    protected $fillable = [
        'name',
        'slug',
        'server',
        'origin_name',
        'description',
        'quality',
        'poster_url',
        'thumbnail_url',
        'trailer_url',
        'time',
        'episode_current',
        'episode_total',
        'year',
        'status_id',
        'type_id',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    public function episode(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function genre(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }

    public function country(): BelongsToMany
    {
        return $this->belongsToMany(Country::class);
    }
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_film', 'film_id', 'user_id');
    }
}
