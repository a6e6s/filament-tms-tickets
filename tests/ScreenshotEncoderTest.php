<?php

use Illuminate\Http\UploadedFile;
use Tms\FilamentTickets\Exceptions\TicketSubmissionException;
use Tms\FilamentTickets\Services\ScreenshotEncoder;
use Tms\FilamentTickets\Tests\TestCase;

uses(TestCase::class);

// 1x1 transparent PNG.
const TMS_TEST_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

function tmsTestImage(string $name, int $bytes = 0): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'tms');
    $content = base64_decode(TMS_TEST_PNG);
    if ($bytes > 0) {
        $content .= str_repeat("\0", max(0, $bytes - strlen($content)));
    }
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

it('encodes a png as a data URI', function () {
    $uris = app(ScreenshotEncoder::class)->encode([tmsTestImage('a.png')]);

    expect($uris)->toHaveCount(1)
        ->and($uris[0])->toStartWith('data:image/png;base64,');
});

it('rejects more than five screenshots', function () {
    $files = array_map(fn ($i) => tmsTestImage("s{$i}.png"), range(1, 6));

    expect(fn () => app(ScreenshotEncoder::class)->encode($files))
        ->toThrow(TicketSubmissionException::class);
});

it('rejects a screenshot over 2 MB', function () {
    expect(fn () => app(ScreenshotEncoder::class)->encode([tmsTestImage('big.png', ScreenshotEncoder::MAX_FILE_BYTES + 1)]))
        ->toThrow(TicketSubmissionException::class);
});

it('rejects screenshots whose combined size would exceed the request budget', function () {
    $files = [
        tmsTestImage('one.png', 2 * 1024 * 1024),
        tmsTestImage('two.png', 2 * 1024 * 1024),
    ];

    expect(fn () => app(ScreenshotEncoder::class)->encode($files))
        ->toThrow(TicketSubmissionException::class, __('tms-tickets::tickets.errors.total_too_large'));
});

it('accepts browser captures as data URIs alongside uploads', function () {
    $uris = app(ScreenshotEncoder::class)->encode(
        [tmsTestImage('a.png')],
        ['data:image/png;base64,'.TMS_TEST_PNG],
    );

    expect($uris)->toHaveCount(2);
});

it('rejects a browser capture that is not a base64 image data URI', function () {
    expect(fn () => app(ScreenshotEncoder::class)->encode([], ['data:text/html;base64,PGgxPg==']))
        ->toThrow(TicketSubmissionException::class, __('tms-tickets::tickets.errors.invalid_type'));
});

it('counts browser captures toward the screenshot limit', function () {
    $captures = array_fill(0, 6, 'data:image/png;base64,'.TMS_TEST_PNG);

    expect(fn () => app(ScreenshotEncoder::class)->encode([], $captures))
        ->toThrow(TicketSubmissionException::class);
});

it('rejects files that are not png, jpeg or webp', function () {
    $path = tempnam(sys_get_temp_dir(), 'tms');
    file_put_contents($path, 'plain text, not an image');
    $file = new UploadedFile($path, 'notes.png', 'image/png', null, true);

    expect(fn () => app(ScreenshotEncoder::class)->encode([$file]))
        ->toThrow(TicketSubmissionException::class, __('tms-tickets::tickets.errors.invalid_type'));
});
