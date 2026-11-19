<?php

use Illuminate\Support\Facades\Http;
use Tms\FilamentTickets\Exceptions\TicketSubmissionException;
use Tms\FilamentTickets\Services\TmsTicketClient;
use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

it('posts the ticket with the bearer token and idempotency key', function () {
    Http::fake(['tms.test/api/v1/tickets' => Http::response(['status' => 'accepted', 'ticket_number' => 'TKT-1', 'tracking_url' => 'https://tms.test/t/1'], 202)]);

    $result = app(TmsTicketClient::class)->submit(['title' => 'Broken button', 'description' => 'Nothing happens'], 'key-123');

    expect($result)->toMatchArray(['ticket_number' => 'TKT-1', 'created' => true]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://tms.test/api/v1/tickets'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Idempotency-Key', 'key-123')
            && $request['title'] === 'Broken button';
    });
});

it('reports a replayed idempotency key as not newly created', function () {
    Http::fake(['tms.test/*' => Http::response(['ticket_number' => 'TKT-1'], 200)]);

    expect(app(TmsTicketClient::class)->submit(['title' => 't', 'description' => 'd'], 'key-1')['created'])->toBeFalse();
});

it('maps 422 reporter and screenshot errors onto form field names', function () {
    Http::fake(['tms.test/*' => Http::response([
        'errors' => [
            'reporter.email' => ['The reporter.email field must be a valid email address.'],
            'screenshots' => ['Each screenshot must be under 2 MB.'],
            'title' => ['The title field is required.'],
        ],
    ], 422)]);

    try {
        app(TmsTicketClient::class)->submit(['title' => '', 'description' => 'd'], 'key-2');
        $this->fail('Expected a TicketSubmissionException.');
    } catch (TicketSubmissionException $exception) {
        expect($exception->fieldErrors)->toHaveKeys(['reporter_email', 'screenshots'])
            ->and($exception->fieldErrors)->not->toHaveKey('title');
    }
});

it('explains a rejected token', function () {
    Http::fake(['tms.test/*' => Http::response(['title' => 'Unauthorized'], 401)]);

    expect(fn () => app(TmsTicketClient::class)->submit(['title' => 't', 'description' => 'd'], 'key-3'))
        ->toThrow(TicketSubmissionException::class, __('tms-tickets::tickets.errors.unauthorized'));
});

it('refuses to send when the TMS url or token is missing', function () {
    config()->set('tms-tickets.token', null);
    Http::fake();

    expect(fn () => app(TmsTicketClient::class)->submit(['title' => 't', 'description' => 'd'], 'key-4'))
        ->toThrow(TicketSubmissionException::class);

    Http::assertNothingSent();
});
