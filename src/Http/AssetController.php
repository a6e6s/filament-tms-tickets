<?php

namespace Tms\FilamentTickets\Http;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController
{
    private const ASSETS = ['html-to-image.js', 'screenshot.js'];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(in_array($file, self::ASSETS, true), Response::HTTP_NOT_FOUND);

        return response()->file(
            __DIR__.'/../../resources/js/'.$file,
            [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-cache',
            ],
        );
    }
}
