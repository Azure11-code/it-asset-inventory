<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signatory extends Model
{
    use HasFactory, Searchable;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = ['name', 'title', 'role'];

    public const ROLES = ['checked_by', 'reviewed_by', 'approved_by'];

    protected $fillable = ['name', 'title', 'role', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
