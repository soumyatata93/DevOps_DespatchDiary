<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(11560000000)->by($request->user()?->id ?: $request->ip());
            //return Limit::none;
        });
        // no limit throttle
    RateLimiter::for('none', function (Request $request) {
        return Limit::none();
    });
        RateLimiter::for('api', function (Request $request) {
            //return Limit::perMinute(11560000000)->by($request->user()?->id ?: $request->ip());
            // $perMinute = env('APP_ENV') === 'testing' ? 1000 : 60;

            return Limit::perMinute($perMinute)
              ->by(optional($request->user())->id ?: $request->ip());
        });
         // no limit throttle
    RateLimiter::for('api', function (Request $request) {
        return Limit::none();
    });
    
    }
}
