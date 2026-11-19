<?php

namespace Tms\FilamentTickets\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Tms\FilamentTickets\Actions\CreateTicketAction;

class CreateTicketButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function createTicketAction(): Action
    {
        return CreateTicketAction::make();
    }

    public function render(): View
    {
        return view('tms-tickets::livewire-button');
    }
}
