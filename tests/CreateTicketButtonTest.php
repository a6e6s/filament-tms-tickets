<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tms\FilamentTickets\Livewire\CreateTicketButton;
use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

it('mounts the create ticket action with the expected fields', function () {
    Livewire::test(CreateTicketButton::class)
        ->call('mountAction', 'createTicket')
        ->assertSchemaComponentExists('title')
        ->assertSchemaComponentExists('device_metadata')
        ->assertSchemaComponentExists('screenshots');
});

it('submits the form to the TMS and reports the ticket number', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-42', 'tracking_url' => null], 202)]);

    Livewire::test(CreateTicketButton::class)
        ->callAction('createTicket', data: [
            'title' => 'Broken export',
            'description' => 'The CSV export is empty.',
            'priority' => 'high',
            'reporter_name' => 'Sam',
            'reporter_email' => 'sam@example.com',
        ]);

    Http::assertSent(fn ($request) => $request['title'] === 'Broken export'
        && $request['priority'] === 'high'
        && $request['reporter']['email'] === 'sam@example.com');
});
