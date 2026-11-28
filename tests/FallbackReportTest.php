<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Http;
use Tms\FilamentTickets\Services\TicketContext;
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

it('parses the operating system and browser from the user agent', function (string $userAgent, array $expected) {
    expect(TicketContext::parseUserAgent($userAgent))->toBe($expected);
})->with([
    'windows chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36', ['os' => 'Windows', 'browser' => 'Chrome']],
    'windows edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0 Safari/537.36 Edg/124.0', ['os' => 'Windows', 'browser' => 'Edge']],
    'mac safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 Version/17.4 Safari/605.1.15', ['os' => 'macOS', 'browser' => 'Safari']],
    'android firefox' => ['Mozilla/5.0 (Android 14; Mobile; rv:125.0) Gecko/125.0 Firefox/125.0', ['os' => 'Android', 'browser' => 'Firefox']],
    'iphone safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 Version/17.4 Mobile/15E148 Safari/604.1', ['os' => 'iOS', 'browser' => 'Safari']],
    'linux firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0', ['os' => 'Linux', 'browser' => 'Firefox']],
    'unknown agent' => ['curl/8.5.0', []],
]);
