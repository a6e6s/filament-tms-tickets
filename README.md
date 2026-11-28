# filament-tms-tickets

A Filament v5 plugin that adds a **Create ticket** button to any panel of a host app. Submitted tickets are sent over HTTP to the TMS ticketing app (`POST /api/v1/tickets`), so the host never touches TMS tables.

Features:

- Title, description, and priority. The reporter and page URL come from the signed-in user and the current page.
- Signed-in user ID, panel, platform label, OS and browser, and the tenant (only when the panel uses Filament tenancy). The Laravel session ID is never sent.
- Screenshots, chosen with a mode switch: **Select an element** (click **Pick an element**, then click part of the page, up to 5), **Upload** (files from disk), or **None** (default). Every captured screenshot appears as a thumbnail in the form, with a Remove button.
- Captures the page URL, user agent, viewport, and recent browser console errors automatically.
- Idempotency key per form, so a retry after a network failure does not create a duplicate ticket.
- English and Arabic labels.
- Opt-in per panel, so multi-panel hosts choose which panels show the button.

## Requirements

- PHP 8.3+, Laravel 13, Filament 5.
- A running TMS instance with the `POST /api/v1/tickets` endpoint.

## 1. Create a SourceApp token in the TMS

1. In the TMS admin, open **Source Apps** and create one entry per host project (for example `my-app`).
2. Use the token action in the table. The plaintext token is shown **once**; copy it.
   The token is created with the `tickets:create` ability only.

Use one token per host project so tokens can be revoked independently.

## 2. Install in the host project

Install the package from Packagist. This also adds it to the host's `composer.json`:

```bash
composer require a6e6s/filament-tms-tickets:^1.0
```

To upgrade an existing install to the latest 1.x release, run `composer update a6e6s/filament-tms-tickets` and then `php artisan optimize:clear`.

Local development alternative (path repository pointing at a checkout, useful for testing changes before a release):

```json
"repositories": [
    { "type": "path", "url": "../filament-tms-tickets", "options": { "symlink": true } }
]
```

## 3. Configure the host `.env`

```dotenv
TMS_TICKETS_URL=https://tms.example.com
TMS_TICKETS_TOKEN=paste-the-sourceapp-token-here
TMS_TICKETS_TIMEOUT=10
# Label shown to TMS staff for this host app. Defaults to APP_NAME.
TMS_TICKETS_PLATFORM="Jit HR"
# Optional kill switch; the button is hidden when false.
TMS_TICKETS_ENABLED=true
```

### What metadata is sent

Each ticket's `device_metadata` includes:

| Key | Source | When |
| --- | --- | --- |
| `user_id` | `auth()->id()` of the signed-in user | Signed-in users only |
| `panel` | Current Filament panel ID | Button in a panel (not the fallback form) |
| `platform` | `TMS_TICKETS_PLATFORM`, else `APP_NAME` | Always |
| `os`, `browser` | Parsed from the user agent | When recognized |
| `tenant` | `{id, name}` of the current Filament tenant | Only when the panel has tenancy and a tenant is set |
| `user_agent`, `viewport`, `console_errors` | Browser | Panel button only (viewport and console errors) |

Systems without tenants send no `tenant` key. The session ID is never read or sent.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=tms-tickets-config
```

Then clear caches:

```bash
php artisan optimize:clear
```

## 4. Enable the plugin per panel

Single-panel app, in `app/Providers/Filament/AdminPanelProvider.php`:

```php
use Tms\FilamentTickets\FilamentTicketsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...existing configuration...
        ->plugins([
            FilamentTicketsPlugin::make(),
        ]);
}
```

Multi-panel app (for example one with `admin`, `crm`, and `employee` panels): add the plugin only to the panels that should report tickets, such as `admin` and `crm`. Panels without the plugin show no button.

Every panel that uses the plugin sends tickets with the host's SourceApp token, so all tickets from one host project share one source.

### Fallback form

The plugin also registers a plain HTML form at `/tms-tickets/report`. It does not use Livewire, Filament, or any JavaScript, so it still works when the panel's assets or a Livewire request are broken. It supports title, description, and priority only; no screenshots. The route requires an authenticated user by default. Change it with `fallback_middleware` in the published config (for example `['web']` to allow anonymous reports, which is not recommended without rate limiting).

## 5. Verify the deployment

1. Confirm the TMS is reachable from the host server:
   ```bash
   curl -sS -o /dev/null -w "%{http_code}\n" https://tms.example.com/up
   ```
2. Open a panel with the plugin, click **Support** (or **Create ticket** in older builds), fill in the form, and submit.
3. Expected result: a success notification with a ticket number, and the ticket appears in the TMS.
4. Screenshots are stored synchronously when the ticket is created, so no queue worker is needed for them. If a screenshot is missing from a ticket, check the TMS logs for a storage error.

## Limits and error messages

| Limit | Value | Reason |
| --- | --- | --- |
| Screenshots per ticket | 5 | TMS `screenshots` max |
| Size per screenshot | 2 MB | TMS per-file limit |
| Combined screenshot size | 3.5 MB | Base64 adds about 33%, so the JSON body stays under the TMS 5 MB request cap |
| Console errors captured | 50 (2000 chars each) | TMS `device_metadata.console_errors` max |

Common failures shown to users:

- **Not configured**: `TMS_TICKETS_URL` or `TMS_TICKETS_TOKEN` is empty.
- **Rejected token (401)**: the token is wrong, revoked, or belongs to another environment.
- **Rate limited (429)**: the TMS ticket-ingestion throttle was hit; wait and retry.
- **Unreachable**: the host cannot reach `TMS_TICKETS_URL`, or the request timed out.

## Troubleshooting

- Panel broken or the button can't open: use the plain fallback form at `/tms-tickets/report`, described below.
- Button missing: check `TMS_TICKETS_ENABLED`, confirm the plugin is listed in that panel's provider, and run `php artisan optimize:clear`.
- Screenshots fail: check the file type (PNG, JPEG, WebP only) and size limits above.
- Screenshots are captured only when the user asks for them. Opening the form stays fast; whole-page capture is not offered because it blocks the browser on large pages.
- Captured elements use html-to-image, which approximates the page. Cross-origin images without CORS headers are skipped, and some CSS effects may not render. Use **Upload** if a capture looks wrong.
- Console errors not captured: they are recorded only after the plugin's script loads on the page.

## Development

```bash
composer install
vendor/bin/pest
```

Tests use Orchestra Testbench and `Http::fake()`, so they do not contact a real TMS.
