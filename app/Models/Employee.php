<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_no', 'first_name', 'middle_name', 'last_name',
        'email', 'contact_no', 'position',
        'department_id', 'location_id',
        'date_hired', 'date_resigned', 'status', 'notes',
    ];

    protected $casts = [
        'date_hired'    => 'date',
        'date_resigned' => 'date',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function heldAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_holder_id');
    }
}
