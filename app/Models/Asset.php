<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The department rule runs on every save, so each of the paths that can
     * move an asset — the form, bulk receive, the importer, and each kind of
     * movement — obeys it without having to remember to.
     */
    protected static function booted(): void
    {
        static::saving(fn (Asset $asset) => $asset->applyDepartmentRule());
    }

    /**
     * Whether this asset sits at a location that is organised into departments.
     *
     * Only such assets carry a department; everywhere else the column stays
     * null and the asset is reported under its location instead.
     */
    public function locationHasDepartments(): bool
    {
        if ($this->current_location_id === null) {
            return false;
        }

        // Read by id rather than through the relation: during a save the
        // relation may still hold the location the asset is moving away from.
        return (bool) Location::whereKey($this->current_location_id)->value('has_departments');
    }

    /**
     * Applies the department rule to an asset about to be saved.
     *
     * - At a location without departments the department is cleared: it would
     *   describe nothing.
     * - At a location with departments a blank department is filled in from
     *   the holder, which is the only place the answer can come from.
     *
     * A department that is already set is never overwritten. That is what
     * keeps it steady when the holder resigns, goes inactive, or hands the
     * asset back — the asset still belongs to the department it was bought
     * for. Changing it takes an explicit edit, or a move to a location
     * without departments.
     */
    public function applyDepartmentRule(): void
    {
        if (! $this->locationHasDepartments()) {
            $this->department_id = null;

            return;
        }

        if ($this->department_id === null && $this->current_holder_id !== null) {
            $this->department_id = Employee::whereKey($this->current_holder_id)->value('department_id');
        }
    }

    /** The endpoint-protection buckets the dashboard reports on. */
    public const ANTIVIRUS_BUCKETS = ['Yes', 'No', 'Excluded', '—'];

    /**
     * Which endpoint-protection bucket an asset falls in, read from its
     * "Antivirus" specification. Any named product counts as installed, so
     * this is not tied to one vendor.
     *
     * The dashboard tally and the assets-list filter both call this, so a
     * number on the dashboard always matches the list it links to.
     */
    public static function antivirusBucket(?array $specifications): string
    {
        $value = collect($specifications ?? [])
            ->first(fn ($s) => is_array($s) && strcasecmp($s['key'] ?? '', 'Antivirus') === 0);

        $raw = is_array($value) ? trim((string) ($value['value'] ?? '')) : '';
        if ($raw === '') {
            return '—';
        }

        return match (strtolower($raw)) {
            'yes', 'y'                 => 'Yes',
            'excluded'                 => 'Excluded',
            'no', 'n', 'none', '-'     => 'No',
            default                    => 'Yes',
        };
    }

    /**
     * Narrows to the assets in one endpoint-protection bucket.
     *
     * The value lives inside a JSON array of {key, value} pairs under a key
     * whose spelling varies by case, so it is classified in PHP and matched
     * by id rather than contorted into SQL.
     */
    public function scopeAntivirus(Builder $query, ?string $bucket): Builder
    {
        if (! in_array($bucket, self::ANTIVIRUS_BUCKETS, true)) {
            return $query;
        }

        $ids = [];
        static::query()
            ->select('id', 'specifications')
            ->chunk(500, function ($chunk) use (&$ids, $bucket) {
                foreach ($chunk as $asset) {
                    if (self::antivirusBucket($asset->specifications) === $bucket) {
                        $ids[] = $asset->id;
                    }
                }
            });

        return $query->whereIn('assets.id', $ids);
    }

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
