<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    private const SORTABLE = ['name', 'username', 'email', 'created_at'];

    public function index(Request $request)
    {
        $sort      = in_array($request->sort, self::SORTABLE, true) ? $request->sort : 'name';
        $direction = $request->direction === 'desc' ? 'desc' : 'asc';

        $users = User::query()
            ->with('permissions:id,user_id,resource,action')
            ->when($request->search, fn ($q, $s) => $q->search($s))

            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'                => $u->id,
                'name'              => $u->name,
                'username'          => $u->username,
                'email'             => $u->email,
                'is_admin'          => (bool) $u->is_admin,
                'permissions'       => $u->permissions
                                          ->map(fn ($p) => "{$p->resource}.{$p->action}")
                                          ->values()
                                          ->all(),
                'email_verified_at' => $u->email_verified_at?->format('Y-m-d H:i'),
                'created_at'        => $u->created_at?->format('Y-m-d'),
                'is_current'        => $u->id === Auth::id(),
            ]);

        return Inertia::render('Users/Index', [
            'users'              => $users,
            'filters'            => $request->only('search', 'sort', 'direction'),
            'permission_catalog' => config('permissions'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'username'      => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email'         => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin'      => ['sometimes', 'boolean'],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:80'],
        ]);

        $user = null;
        DB::transaction(function () use ($data, &$user) {
            $user = User::create([
                'name'              => $data['name'],
                'username'          => strtolower($data['username']),
                'email'             => $data['email'] ?? null,
                'password'          => Hash::make($data['password']),
                'is_admin'          => (bool) ($data['is_admin'] ?? false),
                'email_verified_at' => !empty($data['email']) ? now() : null,
            ]);
            $this->syncPermissions($user, $data['permissions'] ?? []);
        });

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'username'      => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'         => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'      => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin'      => ['sometimes', 'boolean'],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'max:80'],
        ]);

        DB::transaction(function () use ($data, $user) {
            $user->name     = $data['name'];
            $user->username = strtolower($data['username']);
            $user->email    = $data['email'] ?: null;
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            // Prevent editors from demoting the only admin.
            $isSelf         = $user->id === Auth::id();
            $wasAdmin       = (bool) $user->is_admin;
            $willBeAdmin    = (bool) ($data['is_admin'] ?? false);
            $adminCount     = User::where('is_admin', true)->count();
            if ($wasAdmin && !$willBeAdmin && $adminCount <= 1) {
                abort(422, 'At least one admin must exist.');
            }
            $user->is_admin = $willBeAdmin;
            $user->save();

            $this->syncPermissions($user, $data['permissions'] ?? []);
        });

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if (User::count() <= 1) {
            return back()->with('error', 'Cannot delete the last user.');
        }
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'Cannot delete the only admin.');
        }
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    private function syncPermissions(User $user, array $keys): void
    {
        // Keys look like ["assets.view", "assets.edit", ...]
        $catalog = collect(config('permissions.resources'));
        $valid   = [];
        foreach ($keys as $key) {
            [$resource, $action] = array_pad(explode('.', $key, 2), 2, null);
            if (!$resource || !$action) continue;
            $entry = $catalog->firstWhere('key', $resource);
            if (!$entry || !in_array($action, $entry['actions'], true)) continue;
            $valid["{$resource}.{$action}"] = ['resource' => $resource, 'action' => $action];
        }

        $user->permissions()->delete();
        foreach ($valid as $row) {
            $user->permissions()->create($row);
        }
    }
}
