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
}
