<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetPermit extends Model
{
    use HasFactory;

    protected $fillable = [
        'permit_no',
        'employee_id', 'employee_name', 'position_text', 'department_text',
        'destination', 'purpose',
        'date_borrow', 'date_return', 'valid_from', 'valid_to',
        'requested_by_employee_id',
        'issued_by_user_id', 'noted_by_user_id', 'noted_by_secondary_user_id',
        'approved_by_user_id', 'approval_note',
        'status',
    ];

    protected $casts = [
        'date_borrow' => 'date',
        'date_return' => 'date',
        'valid_from'  => 'date',
        'valid_to'    => 'date',
    ];

    public function employee(): BelongsTo          { return $this->belongsTo(Employee::class); }
    public function requestedBy(): BelongsTo       { return $this->belongsTo(Employee::class, 'requested_by_employee_id'); }
    public function issuedBy(): BelongsTo          { return $this->belongsTo(User::class, 'issued_by_user_id'); }
    public function notedBy(): BelongsTo           { return $this->belongsTo(User::class, 'noted_by_user_id'); }
    public function notedBySecondary(): BelongsTo  { return $this->belongsTo(User::class, 'noted_by_secondary_user_id'); }
    public function approvedBy(): BelongsTo        { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function items(): HasMany               { return $this->hasMany(AssetPermitItem::class)->orderBy('sort_order')->orderBy('id'); }

    public static function nextPermitNo(): string
    {
        $year = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('PBA-%s-%04d', $year, $count);
    }
}
