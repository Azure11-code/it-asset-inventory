<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory, Searchable;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = [
        'name', 'description', 'building', 'floor', 'room', 'address',
    ];

    protected $fillable = ['name', 'description', 'building', 'floor', 'room', 'address', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_location_id');
    }
}
