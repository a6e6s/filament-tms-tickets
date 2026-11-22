<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tms\FilamentTickets\Livewire\CreateTicketButton;
use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

const TMS_TEST_CAPTURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

it('mounts the create ticket action with the screenshot options', function () {
    Livewire::test(CreateTicketButton::class)
        ->call('mountAction', 'createTicket')
        ->assertSchemaComponentExists('screenshot_mode')
        ->assertSchemaComponentExists('element_captures')
        ->assertSchemaComponentExists('screenshots');
});

it('submits the form to the TMS and reports the ticket number', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-42', 'tracking_url' => null], 202)]);

    Livewire::test(CreateTicketButton::class)
        ->callAction('createTicket', data: [
            'title' => 'Broken export',
            'description' => 'The CSV export is empty.',
            'priority' => 'high',
            'screenshot_mode' => 'none',
        ]);

    Http::assertSent(fn ($request) => $request['title'] === 'Broken export'
        && $request['priority'] === 'high'
        && ! isset($request['screenshots']));
});

it('sends the selected element captures as screenshots', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-43', 'tracking_url' => null], 202)]);

    Livewire::test(CreateTicketButton::class)
        ->callAction('createTicket', data: [
            'title' => 'Layout broken',
            'description' => 'The sidebar overlaps the table.',
            'priority' => 'medium',
            'screenshot_mode' => 'element',
            'element_captures' => json_encode([TMS_TEST_CAPTURE]),
        ]);

    Http::assertSent(fn ($request) => count($request['screenshots'] ?? []) === 1
        && str_starts_with($request['screenshots'][0], 'data:image/png;base64,'));
});

it('ignores captures that belong to a different screenshot mode', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-44', 'tracking_url' => null], 202)]);

    Livewire::test(CreateTicketButton::class)
        ->callAction('createTicket', data: [
            'title' => 'Stale capture',
            'description' => 'Upload mode should not send element captures.',
            'priority' => 'low',
            'screenshot_mode' => 'upload',
            'element_captures' => json_encode([TMS_TEST_CAPTURE]),
        ]);

    Http::assertSent(fn ($request) => ! isset($request['screenshots']));
});
