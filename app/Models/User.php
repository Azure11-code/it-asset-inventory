<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function hasPermission(string $resource, string $action): bool
    {
        if ($this->is_admin) return true;
        return $this->permissions()
            ->where('resource', $resource)
            ->where('action', $action)
            ->exists();
    }

    /**
     * Flat list like ["assets.view", "assets.edit", ...] for the frontend
     * to check against. Admin gets the full catalog.
     */
    public function permissionList(): array
    {
        if ($this->is_admin) {
            $all = [];
            foreach (config('permissions.resources') as $r) {
                foreach ($r['actions'] as $a) {
                    $all[] = "{$r['key']}.{$a}";
                }
            }
            return $all;
        }
        return $this->permissions()
            ->get(['resource', 'action'])
            ->map(fn ($p) => "{$p->resource}.{$p->action}")
            ->all();
    }
}
