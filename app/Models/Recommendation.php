<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_no', 'report_date',
        'asset_id',
        'requestor_employee_id', 'requestor_name', 'requestor_employee_no',
        'requestor_position', 'requestor_department',
        'thru', 'subject', 'body', 'quick_specs',
        'prepared_by_user_id', 'reviewed_by_user_id', 'noted_by_user_id',
        'status',
    ];

    protected $casts = [
        'report_date' => 'date',
        'quick_specs' => 'array',
    ];

    public function asset(): BelongsTo         { return $this->belongsTo(Asset::class); }
    public function requestor(): BelongsTo     { return $this->belongsTo(Employee::class, 'requestor_employee_id'); }
    public function preparedBy(): BelongsTo    { return $this->belongsTo(User::class, 'prepared_by_user_id'); }
    public function reviewedBy(): BelongsTo    { return $this->belongsTo(User::class, 'reviewed_by_user_id'); }
    public function notedBy(): BelongsTo       { return $this->belongsTo(User::class, 'noted_by_user_id'); }
    public function partChanges(): HasMany     { return $this->hasMany(AssetPartChange::class); }

    public static function nextDocNo(): string
    {
        $year = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('REC-%s-%04d', $year, $count);
    }
}
