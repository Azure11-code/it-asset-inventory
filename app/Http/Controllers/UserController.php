<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                                       ->orWhere('username', 'like', "%{$s}%")
                                       ->orWhere('email', 'like', "%{$s}%"))
            )
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($u) => [
                'id'                => $u->id,
                'name'              => $u->name,
                'username'          => $u->username,
                'email'             => $u->email,
                'email_verified_at' => $u->email_verified_at?->format('Y-m-d H:i'),
                'created_at'        => $u->created_at?->format('Y-m-d'),
                'is_current'        => $u->id === Auth::id(),
            ]);

        return Inertia::render('Users/Index', [
            'users'   => $users,
            'filters' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name'              => $data['name'],
            'username'          => strtolower($data['username']),
            'email'             => $data['email'] ?? null,
            'password'          => Hash::make($data['password']),
            'email_verified_at' => !empty($data['email']) ? now() : null,
        ]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name     = $data['name'];
        $user->username = strtolower($data['username']);
        $user->email    = $data['email'] ?: null;
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

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
        $user->delete();

        return back()->with('success', 'User deleted.');
    }
}
