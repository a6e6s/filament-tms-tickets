<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
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

it('sends the signed-in user id, platform and parsed browser without the session id', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-45', 'tracking_url' => null], 202)]);
    config(['tms-tickets.platform' => 'Jit HR']);
    $this->actingAs(new GenericUser(['id' => 7, 'name' => 'Sara', 'email' => 'sara@client.com']));

    Livewire::test(CreateTicketButton::class)
        ->callAction('createTicket', data: [
            'title' => 'Login loop',
            'description' => 'Signing in redirects back to the login page.',
            'priority' => 'high',
            'screenshot_mode' => 'none',
        ]);

    Http::assertSent(fn ($request) => $request['device_metadata']['user_id'] === '7'
        && $request['device_metadata']['platform'] === 'Jit HR'
        && ! array_key_exists('tenant', $request['device_metadata'])
        && ! str_contains(json_encode($request->data()), session()->getId()));
});

it('sends the current tenant only when the panel uses tenancy', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-46', 'tracking_url' => null], 202)]);
    $this->actingAs(new GenericUser(['id' => 3, 'name' => 'Omar', 'email' => 'omar@client.com']));

    $tenant = new class extends Model {
        protected $guarded = [];
    };
    $tenant->forceFill(['id' => 12, 'name' => 'Acme Clinics']);
    Filament::setCurrentPanel(Panel::make()->id('app')->tenant($tenant::class));
    Filament::setTenant($tenant, isQuiet: true);

    Livewire::test(CreateTicketButton::class)
        ->callAction('createTicket', data: [
            'title' => 'Wrong branch shown',
            'description' => 'Records from another clinic are listed.',
            'priority' => 'low',
            'screenshot_mode' => 'none',
        ]);

    Http::assertSent(fn ($request) => $request['device_metadata']['tenant'] === ['id' => '12', 'name' => 'Acme Clinics']
        && $request['device_metadata']['panel'] === 'app');
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
