<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Visit;
use App\Observers\VisitObserver;
use App\Models\Vacation;
use App\Observers\VacationObserver;

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
        Visit::observe(VisitObserver::class);
        Vacation::observe(VacationObserver::class);
    }
}