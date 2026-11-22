<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Http;
use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Sara Ali', 'email' => 'sara@client.com']));
});

it('renders the plain report form with the referring page prefilled', function () {
    $this->get(route('tms-tickets.report.create', ['page_url' => 'https://app.example.com/admin/orders']))
        ->assertOk()
        ->assertSee('name="title"', false)
        ->assertSee('value="https://app.example.com/admin/orders"', false);
});

it('sends the report with reporter and user agent from the request', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-90', 'tracking_url' => null], 202)]);

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Fallback'])
        ->post(route('tms-tickets.report.store'), [
            'title' => 'Dashboard crashes',
            'description' => 'A PHP error page appears instead of the panel.',
            'priority' => 'critical',
            'page_url' => 'https://app.example.com/admin',
            'idempotency_key' => 'fallback-1',
        ])
        ->assertRedirect()
        ->assertSessionHas('ticket_number', 'TKT-90');

    Http::assertSent(fn ($request) => $request['title'] === 'Dashboard crashes'
        && $request['reporter']['email'] === 'sara@client.com'
        && $request['device_metadata']['user_agent'] === 'Mozilla/5.0 Fallback');
});

it('returns validation errors without contacting the TMS', function () {
    Http::fake();

    $this->post(route('tms-tickets.report.store'), [
        'title' => '',
        'description' => 'Missing a title.',
        'priority' => 'urgent',
        'idempotency_key' => 'fallback-2',
    ])->assertSessionHasErrors(['title', 'priority']);

    Http::assertNothingSent();
});
