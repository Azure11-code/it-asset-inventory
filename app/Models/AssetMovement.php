<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'type',
        'from_employee_id', 'to_employee_id',
        'from_location_id', 'to_location_id',
        'movement_date', 'performed_by',
        'reference', 'remarks',
    ];

    protected $casts = [
        'movement_date' => 'date',
    ];

    public function asset(): BelongsTo        { return $this->belongsTo(Asset::class); }
    public function fromEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'from_employee_id'); }
    public function toEmployee(): BelongsTo   { return $this->belongsTo(Employee::class, 'to_employee_id'); }
    public function fromLocation(): BelongsTo { return $this->belongsTo(Location::class, 'from_location_id'); }
    public function toLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'to_location_id'); }
    public function performer(): BelongsTo    { return $this->belongsTo(User::class, 'performed_by'); }
}
