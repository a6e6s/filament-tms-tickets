<?php

namespace Tms\FilamentTickets\Services;

use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Server-side context merged into device_metadata: who is signed in, which
 * panel and tenant they are in, and a coarse OS/browser from the user agent.
 */
class TicketContext
{
    /**
     * @return array<string, mixed>
     */
    public function forRequest(?object $user, ?string $userAgent, ?string $panelId): array
    {
        $userId = $user?->getAuthIdentifier();

        return array_filter([
            'user_id' => $userId === null ? null : (string) $userId,
            'panel' => $panelId,
            'platform' => (string) (config('tms-tickets.platform') ?: config('app.name')),
            ...self::parseUserAgent($userAgent),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Only panels with tenancy enabled send a tenant. Systems without tenants send nothing.
     * Called only from panel actions, never from the panel-less fallback form.
     *
     * @return array{id: string, name: string}|null
     */
    public static function tenant(): ?array
    {
        if (! Filament::getCurrentPanel()?->hasTenancy()) {
            return null;
        }

        $tenant = Filament::getTenant();

        if (! $tenant instanceof Model) {
            return null;
        }

        $name = Filament::getTenantName($tenant);

        return [
            'id' => (string) $tenant->getKey(),
            'name' => $name instanceof Htmlable ? strip_tags($name->toHtml()) : (string) $name,
        ];
    }

    /**
     * @return array{os?: string, browser?: string}
     */
    public static function parseUserAgent(?string $userAgent): array
    {
        if ($userAgent === null || $userAgent === '') {
            return [];
        }

        return array_filter([
            'os' => self::operatingSystem($userAgent),
            'browser' => self::browser($userAgent),
        ]);
    }

    private static function operatingSystem(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'iPod') => 'iOS',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };
    }

    private static function browser(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') || str_contains($ua, 'Edge/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') || str_contains($ua, 'Chromium/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
    }
}
