<?php

use Illuminate\Support\Arr;
use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

it('keeps the English and Arabic translation keys in sync', function () {
    $en = array_keys(Arr::dot(require __DIR__.'/../resources/lang/en/tickets.php'));
    $ar = array_keys(Arr::dot(require __DIR__.'/../resources/lang/ar/tickets.php'));

    expect($ar)->toEqualCanonicalizing($en);
});
