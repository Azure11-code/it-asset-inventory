<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentReport extends Model
{
    use HasAttachments, HasFactory, Searchable;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = [
        'ir_no', 'end_user_name', 'reported_problem', 'action_taken',
        'findings', 'recommendation', 'status',
        'asset.asset_tag', 'asset.serial_number', 'asset.model',
        'endUser.employee_no', 'endUser.concat:first_name,middle_name,last_name',
        'partChanges.part_name', 'partChanges.old_value', 'partChanges.new_value',
    ];

    protected $fillable = [
        'ir_no',
        'asset_id',
        'end_user_employee_id', 'end_user_name',
        'reported_problem', 'action_taken', 'findings', 'recommendation',
        'prepared_by_user_id',
        'noted_by_user_id', 'noted_by_secondary_user_id',
        'approved_by_user_id',
        'report_date', 'status',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function asset(): BelongsTo            { return $this->belongsTo(Asset::class); }
    public function endUser(): BelongsTo          { return $this->belongsTo(Employee::class, 'end_user_employee_id'); }
    public function preparedBy(): BelongsTo       { return $this->belongsTo(User::class, 'prepared_by_user_id'); }
    public function notedBy(): BelongsTo          { return $this->belongsTo(User::class, 'noted_by_user_id'); }
    public function notedBySecondary(): BelongsTo { return $this->belongsTo(User::class, 'noted_by_secondary_user_id'); }
    public function approvedBy(): BelongsTo       { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function partChanges(): HasMany        { return $this->hasMany(AssetPartChange::class); }

    public static function nextDocNo(): string
    {
        $year = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('IR-%s-%04d', $year, $count);
    }
}
