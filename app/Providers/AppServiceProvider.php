<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind Cloudflare the browser talks HTTPS while the origin may receive
        // plain HTTP. When APP_URL is https://, generate https links and mark the
        // session cookie secure so it is not dropped by the browser.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');

            if (config('session.secure') === null) {
                config(['session.secure' => true]);
            }
        }
    }
}
