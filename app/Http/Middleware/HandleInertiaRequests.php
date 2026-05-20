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
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'counts' => fn () => [
                'assets'      => \App\Models\Asset::count(),
                'users'       => \App\Models\User::count(),
                'employees'   => \App\Models\Employee::count(),
                'departments' => \App\Models\Department::count(),
                'categories'  => \App\Models\Category::count(),
                'brands'      => \App\Models\Brand::count(),
                'conditions'  => \App\Models\Condition::count(),
                'locations'   => \App\Models\Location::count(),
            ],
        ]);
    }
}
