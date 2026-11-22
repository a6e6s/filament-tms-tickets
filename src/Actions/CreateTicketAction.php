<?php

namespace Tms\FilamentTickets\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
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
                Radio::make('screenshot_mode')
                    ->label(__('tms-tickets::tickets.fields.screenshot_mode'))
                    ->options([
                        'auto' => __('tms-tickets::tickets.modes.auto'),
                        'element' => __('tms-tickets::tickets.modes.element'),
                        'upload' => __('tms-tickets::tickets.modes.upload'),
                        'none' => __('tms-tickets::tickets.modes.none'),
                    ])
                    ->default('auto')
                    ->inline()
                    ->live(),
                ViewField::make('element_picker')
                    ->hiddenLabel()
                    ->view('tms-tickets::element-picker')
                    ->visible(fn (Get $get): bool => $get('screenshot_mode') === 'element'),
                FileUpload::make('screenshots')
                    ->label(__('tms-tickets::tickets.fields.screenshots'))
                    ->helperText(__('tms-tickets::tickets.help.screenshots'))
                    ->image()
                    ->multiple()
                    ->maxFiles(ScreenshotEncoder::MAX_COUNT)
                    ->maxSize(ScreenshotEncoder::MAX_FILE_BYTES / 1024)
                    ->storeFiles(false)
                    ->visible(fn (Get $get): bool => $get('screenshot_mode') === 'upload'),
                self::hiddenField('auto_capture')
                    ->extraInputAttributes([
                        'x-init' => 'window.TmsTickets?.bindAuto($el)',
                    ]),
                self::hiddenField('element_captures')
                    ->extraInputAttributes([
                        'x-init' => 'window.TmsTickets?.bindElements($el)',
                    ]),
                self::hiddenField('page_url')
                    ->url()
                    ->maxLength(2048)
                    ->extraInputAttributes([
                        'x-init' => 'if (! $el.value) { $el.value = window.location.href; $el.dispatchEvent(new Event(\'input\')); $el.dispatchEvent(new Event(\'change\')); }',
                    ]),
                self::hiddenField('device_metadata')
                    ->extraInputAttributes([
                        'x-init' => 'const meta = { user_agent: navigator.userAgent, viewport: `${window.innerWidth}x${window.innerHeight}`, console_errors: window.__tmsConsoleErrors ?? [] }; $el.value = JSON.stringify(meta); $el.dispatchEvent(new Event(\'input\')); $el.dispatchEvent(new Event(\'change\'));',
                    ]),
                Hidden::make('idempotency_key')
                    ->default(fn (): string => TmsTicketClient::newIdempotencyKey()),
            ])
            ->action(function (array $data, Action $action): void {
                $user = auth()->user();
                $mode = (string) ($data['screenshot_mode'] ?? 'none');

                $payload = [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'priority' => $data['priority'] ?? 'medium',
                    'page_url' => $data['page_url'] ?? null,
                    'reporter' => array_filter([
                        'name' => $user?->name,
                        'email' => $user?->email,
                    ]),
                    'device_metadata' => json_decode((string) ($data['device_metadata'] ?? ''), true) ?: null,
                ];

                $files = $mode === 'upload' ? array_values(array_filter((array) ($data['screenshots'] ?? []))) : [];
                $captures = match ($mode) {
                    'auto' => self::decodeCaptures($data['auto_capture'] ?? null),
                    'element' => self::decodeCaptures($data['element_captures'] ?? null),
                    default => [],
                };

                try {
                    $encoded = app(ScreenshotEncoder::class)->encode($files, $captures);
                    if ($encoded !== []) {
                        $payload['screenshots'] = $encoded;
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

    /**
     * Hidden fields are filled by screenshot.js and the page context script.
     */
    private static function hiddenField(string $name): TextInput
    {
        return TextInput::make($name)
            ->hiddenLabel()
            ->extraFieldWrapperAttributes(['style' => 'display: none']);
    }

    /**
     * @return array<int, string>
     */
    private static function decodeCaptures(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
