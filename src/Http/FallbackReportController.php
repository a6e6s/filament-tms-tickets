<?php

namespace Tms\FilamentTickets\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Tms\FilamentTickets\Exceptions\TicketSubmissionException;
use Tms\FilamentTickets\Services\TmsTicketClient;

/**
 * Plain HTML report form. It does not use Livewire or Filament, so it still
 * works when the panel UI is broken.
 */
class FallbackReportController
{
    public function create(Request $request): View
    {
        return view('tms-tickets::fallback-report', [
            'pageUrl' => (string) ($request->query('page_url') ?: url()->previous()),
            'idempotencyKey' => TmsTicketClient::newIdempotencyKey(),
            'ticketNumber' => session('ticket_number'),
        ]);
    }

    public function store(Request $request, TmsTicketClient $client): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ])->validate();

        $user = $request->user();

        $payload = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'page_url' => $validated['page_url'] ?? null,
            'reporter' => array_filter([
                'name' => $user?->name,
                'email' => $user?->email,
            ]),
            'device_metadata' => array_filter([
                'user_agent' => $request->userAgent(),
            ]),
        ];

        try {
            $result = $client->submit($payload, $validated['idempotency_key']);
        } catch (TicketSubmissionException $exception) {
            return back()
                ->withInput()
                ->withErrors(['ticket' => $exception->getMessage()]);
        }

        return redirect()
            ->route('tms-tickets.report.create', ['page_url' => $validated['page_url'] ?? null])
            ->with('ticket_number', $result['ticket_number']);
    }
}
