<?php

namespace Tms\FilamentTickets;

use Illuminate\Support\ServiceProvider;

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

        $this->publishes([
            __DIR__.'/../config/tms-tickets.php' => config_path('tms-tickets.php'),
        ], 'tms-tickets-config');
    }
}
