<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCodeRule extends Model
{
    protected $fillable = [
        'label', 'prefix_start', 'prefix_end', 'category_id', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'prefix_start' => 'integer',
        'prefix_end'   => 'integer',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Return the next available asset tag for this rule, or null if the range is full.
     * Format: {YEAR}AIM{4-digit-number}
     */
    public function nextTag(?int $year = null): ?array
    {
        $year = $year ?: (int) date('Y');
        $prefix = "{$year}AIM";

        // Extract the numeric portion of every existing tag that fits the pattern.
        // Uses a pattern match — pulls "1099" out of "2026AIM1099".
        $used = Asset::query()
            ->where('asset_tag', 'like', "{$prefix}%")
            ->pluck('asset_tag')
            ->map(function (string $tag) use ($prefix) {
                $num = substr($tag, strlen($prefix));
                return is_numeric($num) ? (int) $num : null;
            })
            ->filter(fn ($n) => $n !== null && $n >= $this->prefix_start && $n <= $this->prefix_end)
            ->values();

        // Find the smallest unused number in [start, end].
        $sorted = $used->sort()->values();
        $next   = $this->prefix_start;
        foreach ($sorted as $n) {
            if ($n > $next) break; // gap found
            if ($n === $next) $next++;
        }

        if ($next > $this->prefix_end) return null;

        return [
            'number'    => $next,
            'formatted' => $prefix . str_pad($next, 4, '0', STR_PAD_LEFT),
            'year'      => $year,
        ];
    }
}
