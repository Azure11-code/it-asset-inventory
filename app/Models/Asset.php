<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Asset extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = [
        'asset_tag', 'serial_number', 'model', 'description', 'specifications',
        'vendor', 'notes', 'current_status',
        'brand.name',
        'category.name', 'category.prefix',
        'condition.name',
        'currentHolder.employee_no', 'currentHolder.position',
        'currentHolder.concat:first_name,middle_name,last_name',
        'currentLocation.name', 'currentLocation.building',
        'currentLocation.floor', 'currentLocation.room',
        'department.name', 'department.code',
    ];

    protected $fillable = [
        'asset_tag', 'serial_number', 'model', 'description', 'specifications',
        'brand_id', 'category_id', 'condition_id',
        'purchase_date', 'deployment_date', 'purchase_cost', 'vendor',
        'expected_lifespan_years', 'warranty_until',
        'current_status',
        'current_holder_id', 'current_location_id', 'department_id',
        'replaced_by_asset_id', 'replaces_asset_id',
        'notes',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'deployment_date' => 'date',
        'warranty_until'  => 'date',
        'purchase_cost'   => 'decimal:2',
        'specifications'  => 'array',
    ];

    protected $appends = [
        'age_years', 'age_formatted',
        'service_duration_formatted',
        'is_eligible_for_replacement',
        'warranty_status',
    ];

    public function brand(): BelongsTo            { return $this->belongsTo(Brand::class); }
    public function category(): BelongsTo         { return $this->belongsTo(Category::class); }
    public function condition(): BelongsTo        { return $this->belongsTo(Condition::class); }
    public function currentHolder(): BelongsTo    { return $this->belongsTo(Employee::class, 'current_holder_id'); }
    public function currentLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'current_location_id'); }
    public function department(): BelongsTo       { return $this->belongsTo(Department::class); }
    public function replacedBy(): BelongsTo       { return $this->belongsTo(Asset::class, 'replaced_by_asset_id'); }
    public function replaces(): BelongsTo         { return $this->belongsTo(Asset::class, 'replaces_asset_id'); }
    public function movements(): HasMany          { return $this->hasMany(AssetMovement::class)->orderByDesc('movement_date')->orderByDesc('id'); }
    public function partChanges(): HasMany        { return $this->hasMany(AssetPartChange::class)->orderByDesc('changed_at')->orderByDesc('id'); }

    public static function formatYearsMonths(?Carbon $start, ?Carbon $end = null): ?string
    {
        if (!$start) return null;
        $end = $end ?? Carbon::now();
        if ($start->greaterThan($end)) [$start, $end] = [$end, $start];

        $years       = (int) floor($start->diffInYears($end));
        $afterYears  = $start->copy()->addYears($years);
        $months      = (int) floor($afterYears->diffInMonths($end));
        $afterMonths = $afterYears->copy()->addMonths($months);
        $days        = (int) floor($afterMonths->diffInDays($end));

        if ($years === 0 && $months === 0 && $days === 0) return 'today';

        $parts = [];
        if ($years > 0)  $parts[] = $years  . ' year'  . ($years === 1  ? '' : 's');
        if ($months > 0) $parts[] = $months . ' month' . ($months === 1 ? '' : 's');
        if ($days > 0)   $parts[] = $days   . ' day'   . ($days === 1   ? '' : 's');

        return implode(' and ', $parts);
    }

    protected function ageYears(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->purchase_date) return null;
            return round(Carbon::parse($this->purchase_date)->floatDiffInYears(now()), 1);
        });
    }

    protected function ageFormatted(): Attribute
    {
        return Attribute::get(fn () => self::formatYearsMonths(
            $this->purchase_date ? Carbon::parse($this->purchase_date) : null
        ));
    }

    protected function serviceDurationFormatted(): Attribute
    {
        return Attribute::get(fn () => self::formatYearsMonths(
            $this->deployment_date ? Carbon::parse($this->deployment_date) : null
        ));
    }

    protected function isEligibleForReplacement(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->purchase_date) return false;
            return Carbon::parse($this->purchase_date)->diffInYears(now()) >= $this->expected_lifespan_years;
        });
    }

    protected function warrantyStatus(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->warranty_until) return 'unknown';
            $until = Carbon::parse($this->warranty_until);
            if ($until->isPast()) return 'expired';
            if ($until->diffInDays(now()) <= 90) return 'expiring_soon';
            return 'active';
        });
    }
}
