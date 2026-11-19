<?php

namespace Tms\FilamentTickets;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class FilamentTicketsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'tms-tickets';
    }

    public function register(Panel $panel): void
    {
        if (! config('tms-tickets.enabled')) {
            return;
        }

        $panel
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): string => view('tms-tickets::button')->render())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('tms-tickets::console-capture')->render());
    }

    public function boot(Panel $panel): void {}
}
