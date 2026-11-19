<?php

namespace Tms\FilamentTickets\Exceptions;

use RuntimeException;

class TicketSubmissionException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $fieldErrors  Keyed by form field name.
     */
    public function __construct(
        string $message,
        public readonly array $fieldErrors = [],
    ) {
        parent::__construct($message);
    }
}
