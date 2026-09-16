<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPartChange extends Model
{
    use HasFactory, Searchable;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = [
        'part_name', 'old_value', 'new_value', 'reason', 'notes',
        'asset.asset_tag', 'asset.serial_number',
    ];

    protected $fillable = [
        'asset_id', 'incident_report_id', 'recommendation_id',
        'part_name', 'old_value', 'new_value',
        'reason', 'changed_at', 'performed_by_user_id', 'notes',
    ];

    protected $casts = [
        'changed_at' => 'date',
    ];

    public function asset(): BelongsTo          { return $this->belongsTo(Asset::class); }
    public function incidentReport(): BelongsTo { return $this->belongsTo(IncidentReport::class); }
    public function recommendation(): BelongsTo { return $this->belongsTo(Recommendation::class); }
    public function performer(): BelongsTo      { return $this->belongsTo(User::class, 'performed_by_user_id'); }
}
