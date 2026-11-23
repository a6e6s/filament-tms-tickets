<?php

use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

it('serves the screenshot script from an extensionless URL', function () {
    $this->get(route('tms-tickets.asset', 'screenshot'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
});

it('does not serve files outside the whitelist', function () {
    $this->get('/tms-tickets/assets/TestCase.php')->assertNotFound();
    $this->get('/tms-tickets/assets/screenshot.js')->assertNotFound();
});
