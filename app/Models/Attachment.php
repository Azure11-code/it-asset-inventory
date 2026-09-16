<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use Searchable;

    /** Every field a search keyword may hit. @see Searchable */
    protected static array $searchable = ['original_name', 'label', 'mime_type'];

    protected $fillable = [
        'attachable_type', 'attachable_id',
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
        'label', 'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Absolute filesystem path — used when streaming to browser.
     */
    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Delete the actual file when the row is removed.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $a) {
            try {
                Storage::disk($a->disk)->delete($a->path);
            } catch (\Throwable $e) { /* swallow — record still removed */ }
        });
    }
}
