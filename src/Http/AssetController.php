<?php

namespace Tms\FilamentTickets\Http;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController
{
    // Served without a file extension: production nginx serves any *.js URL
    // straight from public/, so a .js route would return 404 there.
    private const ASSETS = [
        'html-to-image' => 'html-to-image.js',
        'screenshot' => 'screenshot.js',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(isset(self::ASSETS[$file]), Response::HTTP_NOT_FOUND);

        return response()->file(
            __DIR__.'/../../resources/js/'.self::ASSETS[$file],
            [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-cache',
            ],
        );
    }
}
