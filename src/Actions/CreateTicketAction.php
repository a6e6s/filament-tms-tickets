<?php

namespace Tms\FilamentTickets\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Tms\FilamentTickets\Exceptions\TicketSubmissionException;
use Tms\FilamentTickets\Services\ScreenshotEncoder;
use Tms\FilamentTickets\Services\TmsTicketClient;

class CreateTicketAction
{
    public static function make(): Action
    {
        return Action::make('createTicket')
            ->label(__('tms-tickets::tickets.button'))
            ->icon('heroicon-o-ticket')
            ->modalHeading(__('tms-tickets::tickets.heading'))
            ->modalSubmitActionLabel(__('tms-tickets::tickets.submit'))
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                TextInput::make('title')
                    ->label(__('tms-tickets::tickets.fields.title'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label(__('tms-tickets::tickets.fields.description'))
                    ->required()
                    ->rows(5)
                    ->maxLength(10000),
                Select::make('priority')
                    ->label(__('tms-tickets::tickets.fields.priority'))
                    ->options([
                        'low' => __('tms-tickets::tickets.priorities.low'),
                        'medium' => __('tms-tickets::tickets.priorities.medium'),
                        'high' => __('tms-tickets::tickets.priorities.high'),
                        'critical' => __('tms-tickets::tickets.priorities.critical'),
                    ])
                    ->default('medium')
                    ->required(),
                TextInput::make('page_url')
                    ->label(__('tms-tickets::tickets.fields.page_url'))
                    ->url()
                    ->maxLength(2048)
                    ->extraInputAttributes([
                        'x-init' => 'if (! $el.value) { $el.value = window.location.href; $el.dispatchEvent(new Event(\'input\')); }',
                    ]),
                TextInput::make('reporter_name')
                    ->label(__('tms-tickets::tickets.fields.reporter_name'))
                    ->default(fn (): ?string => auth()->user()?->name)
                    ->maxLength(255),
                TextInput::make('reporter_email')
                    ->label(__('tms-tickets::tickets.fields.reporter_email'))
                    ->email()
                    ->default(fn (): ?string => auth()->user()?->email)
                    ->maxLength(255),
                FileUpload::make('screenshots')
                    ->label(__('tms-tickets::tickets.fields.screenshots'))
                    ->helperText(__('tms-tickets::tickets.help.screenshots'))
                    ->image()
                    ->multiple()
                    ->maxFiles(ScreenshotEncoder::MAX_COUNT)
                    ->maxSize(ScreenshotEncoder::MAX_FILE_BYTES / 1024)
                    ->storeFiles(false),
                TextInput::make('device_metadata')
                    ->hiddenLabel()
                    ->extraFieldWrapperAttributes(['style' => 'display: none'])
                    ->extraInputAttributes([
                        'x-init' => 'const meta = { user_agent: navigator.userAgent, viewport: window.innerWidth + "x" + window.innerHeight, console_errors: window.__tmsConsoleErrors ?? [] }; $el.value = JSON.stringify(meta); $el.dispatchEvent(new Event(\'input\'));',
                    ]),
                Hidden::make('idempotency_key')
                    ->default(fn (): string => TmsTicketClient::newIdempotencyKey()),
            ])
            ->action(function (array $data, Action $action): void {
                $payload = [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'priority' => $data['priority'] ?? 'medium',
                    'page_url' => $data['page_url'] ?? null,
                    'reporter' => array_filter([
                        'name' => $data['reporter_name'] ?? null,
                        'email' => $data['reporter_email'] ?? null,
                    ]),
                    'device_metadata' => json_decode((string) ($data['device_metadata'] ?? ''), true) ?: null,
                ];

                try {
                    $files = array_values(array_filter((array) ($data['screenshots'] ?? [])));
                    if ($files !== []) {
                        $payload['screenshots'] = app(ScreenshotEncoder::class)->encode($files);
                    }

                    $result = app(TmsTicketClient::class)->submit($payload, (string) $data['idempotency_key']);
                } catch (TicketSubmissionException $exception) {
                    $body = collect($exception->fieldErrors)->flatten()->implode(' ') ?: $exception->getMessage();

                    Notification::make()
                        ->danger()
                        ->title(__('tms-tickets::tickets.errors.failed'))
                        ->body($body)
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('tms-tickets::tickets.success.title'))
                    ->body(__('tms-tickets::tickets.success.body', ['number' => $result['ticket_number']]))
                    ->send();
            });
    }
}
