<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPermitItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_permit_id', 'asset_id',
        'qty', 'unit', 'description', 'serial_no', 'remarks', 'sort_order',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'sort_order' => 'integer',
    ];

    public function permit(): BelongsTo { return $this->belongsTo(AssetPermit::class, 'asset_permit_id'); }
    public function asset(): BelongsTo  { return $this->belongsTo(Asset::class); }
}
