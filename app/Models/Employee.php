<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasAttachments, HasFactory, Searchable;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = [
        'employee_no', 'first_name', 'middle_name', 'last_name',
        'concat:first_name,middle_name,last_name',
        'email', 'contact_no', 'position', 'notes', 'status',
        'department.name', 'department.code',
        'location.name', 'location.building', 'location.floor', 'location.room',
    ];

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

    // ── Reverse relationships (for the employee Show/relationships page) ──

    public function movementsIn(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'to_employee_id');
    }

    public function movementsOut(): HasMany
    {
        return $this->hasMany(AssetMovement::class, 'from_employee_id');
    }

    public function permits(): HasMany
    {
        return $this->hasMany(AssetPermit::class, 'employee_id');
    }

    public function permitsRequested(): HasMany
    {
        return $this->hasMany(AssetPermit::class, 'requested_by_employee_id');
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'end_user_employee_id');
    }

    public function recommendationsFiled(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'requestor_employee_id');
    }
}
