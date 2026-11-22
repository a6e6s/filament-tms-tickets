<div>
    <x-filament::button
        color="gray"
        icon="heroicon-o-ticket"
        size="sm"
        wire:click="mountAction('createTicket')"
    >
        {{ __('tms-tickets::tickets.button') }}
    </x-filament::button>

    <x-filament-actions::modals />
</div>
