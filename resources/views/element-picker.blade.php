<div
    x-data="{ count: 0, error: false }"
    x-on:tms-tickets:captures.window="
        if ($event.detail.error) { error = true; return; }
        error = false;
        if ($event.detail.count !== undefined) { count = $event.detail.count; }
    "
    class="space-y-2"
>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button
            type="button"
            color="gray"
            size="sm"
            icon="heroicon-o-cursor-arrow-rays"
            x-on:click="window.TmsTickets?.pick()"
        >
            {{ __('tms-tickets::tickets.element.pick') }}
        </x-filament::button>

        <x-filament::button
            type="button"
            color="gray"
            size="sm"
            outlined
            x-on:click="window.TmsTickets?.clear()"
        >
            {{ __('tms-tickets::tickets.element.clear') }}
        </x-filament::button>

        <span class="text-sm text-gray-600 dark:text-gray-400" x-text="`${count} / {{ \Tms\FilamentTickets\Services\ScreenshotEncoder::MAX_COUNT }}`"></span>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('tms-tickets::tickets.element.hint') }}</p>

    <p class="text-sm text-danger-600 dark:text-danger-400" x-show="error" x-cloak>
        {{ __('tms-tickets::tickets.errors.capture_failed') }}
    </p>
</div>
