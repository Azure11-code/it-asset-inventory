<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user'        => $user ? [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'username' => $user->username,
                    'email'    => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                ] : null,
                'permissions' => $user ? $user->permissionList() : [],
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'flash' => [
                'success'      => fn () => $request->session()->get('success'),
                'error'        => fn () => $request->session()->get('error'),
                'import_skips' => fn () => $request->session()->get('import_skips'),
            ],
            'counts' => fn () => [
                'assets'      => \App\Models\Asset::count(),
                'users'       => \App\Models\User::count(),
                'employees'   => \App\Models\Employee::count(),
                'permits'     => \App\Models\AssetPermit::count(),
                'incidents'   => \App\Models\IncidentReport::count(),
                'recommendations' => \App\Models\Recommendation::count(),
                'departments' => \App\Models\Department::count(),
                'categories'  => \App\Models\Category::count(),
                'brands'      => \App\Models\Brand::count(),
                'conditions'  => \App\Models\Condition::count(),
                'locations'   => \App\Models\Location::count(),
                'assetCodeRules' => \App\Models\AssetCodeRule::count(),
            ],
        ]);
    }
}
