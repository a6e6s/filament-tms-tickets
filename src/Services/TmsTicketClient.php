<?php

namespace Tms\FilamentTickets\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tms\FilamentTickets\Exceptions\TicketSubmissionException;

class TmsTicketClient
{
    /**
     * Maps the TMS reporter.* and screenshots keys onto the form field names.
     */
    private const FIELD_MAP = [
        'reporter.name' => 'reporter_name',
        'reporter.email' => 'reporter_email',
        'screenshots' => 'screenshots',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ticket_number: string, tracking_url: ?string, created: bool}
     *
     * @throws TicketSubmissionException
     */
    public function submit(array $payload, string $idempotencyKey): array
    {
        $baseUrl = rtrim((string) config('tms-tickets.url'), '/');
        $token = (string) config('tms-tickets.token');

        if ($baseUrl === '' || $token === '') {
            throw new TicketSubmissionException(__('tms-tickets::tickets.errors.not_configured'));
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->timeout((int) config('tms-tickets.timeout', 10))
                ->post($baseUrl.'/api/v1/tickets', $payload);
        } catch (ConnectionException) {
            throw new TicketSubmissionException(__('tms-tickets::tickets.errors.unreachable'));
        }

        if ($response->successful()) {
            return [
                'ticket_number' => (string) $response->json('ticket_number'),
                'tracking_url' => $response->json('tracking_url'),
                'created' => $response->status() === 202,
            ];
        }

        throw $this->translateFailure($response->status(), $response->json('errors') ?? []);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function translateFailure(int $status, array $errors): TicketSubmissionException
    {
        if ($status === 422) {
            $fieldErrors = [];
            foreach ($errors as $key => $messages) {
                $field = self::FIELD_MAP[$key] ?? null;
                if ($field !== null) {
                    $fieldErrors[$field] = (array) $messages;
                }
            }

            return new TicketSubmissionException(__('tms-tickets::tickets.errors.validation'), $fieldErrors);
        }

        $message = match (true) {
            $status === 401 => __('tms-tickets::tickets.errors.unauthorized'),
            $status === 413 => __('tms-tickets::tickets.errors.payload_too_large'),
            $status === 429 => __('tms-tickets::tickets.errors.rate_limited'),
            $status >= 500 => __('tms-tickets::tickets.errors.server'),
            default => __('tms-tickets::tickets.errors.generic', ['status' => $status]),
        };

        return new TicketSubmissionException($message);
    }

    public static function newIdempotencyKey(): string
    {
        return (string) Str::uuid();
    }
}
