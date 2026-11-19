<?php

namespace Tms\FilamentTickets\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Tms\FilamentTickets\Exceptions\TicketSubmissionException;

class ScreenshotEncoder
{
    /**
     * The TMS caps each screenshot at 2 MB and the whole request body at 5 MB.
     * Base64 adds about 33%, so raw bytes are capped to keep the JSON body under 5 MB.
     */
    public const MAX_COUNT = 5;

    public const MAX_FILE_BYTES = 2 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 3_500_000;

    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string> data URIs
     *
     * @throws TicketSubmissionException
     */
    public function encode(array $files): array
    {
        if (count($files) > self::MAX_COUNT) {
            $message = __('tms-tickets::tickets.errors.too_many_screenshots', ['max' => self::MAX_COUNT]);

            throw new TicketSubmissionException($message, ['screenshots' => [$message]]);
        }

        $uris = [];
        $total = 0;
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($files as $file) {
            $path = $file->getRealPath();
            $size = $path === false ? 0 : (int) filesize($path);
            $mime = $path === false ? '' : (string) $finfo->file($path);

            if (! in_array($mime, self::ALLOWED_MIME, true)) {
                throw $this->fieldError('screenshots', __('tms-tickets::tickets.errors.invalid_type'));
            }

            if ($size > self::MAX_FILE_BYTES) {
                throw $this->fieldError('screenshots', __('tms-tickets::tickets.errors.file_too_large'));
            }

            $total += $size;
            if ($total > self::MAX_TOTAL_BYTES) {
                throw $this->fieldError('screenshots', __('tms-tickets::tickets.errors.total_too_large'));
            }

            $uris[] = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
        }

        return $uris;
    }

    private function fieldError(string $field, string $message): TicketSubmissionException
    {
        return new TicketSubmissionException($message, [$field => [$message]]);
    }
}
