<?php

namespace Tms\FilamentTickets;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Tms\FilamentTickets\Http\AssetController;
use Tms\FilamentTickets\Http\FallbackReportController;

class FilamentTicketsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tms-tickets.php', 'tms-tickets');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tms-tickets');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tms-tickets');

        Route::get('tms-tickets/assets/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9.\-]+')
            ->name('tms-tickets.asset');

        Route::middleware(config('tms-tickets.fallback_middleware', ['web', 'auth']))
            ->prefix('tms-tickets/report')
            ->group(function (): void {
                Route::get('/', [FallbackReportController::class, 'create'])->name('tms-tickets.report.create');
                Route::post('/', [FallbackReportController::class, 'store'])->name('tms-tickets.report.store');
            });

        $this->publishes([
            __DIR__.'/../config/tms-tickets.php' => config_path('tms-tickets.php'),
        ], 'tms-tickets-config');
    }
}
