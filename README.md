# filament-tms-tickets

A Filament v5 plugin that adds a **Create ticket** button to any panel of a host app. Submitted tickets are sent over HTTP to the TMS ticketing app (`POST /api/v1/tickets`), so the host never touches TMS tables.

Features:

- Title, description, priority, page URL, reporter name and email, and up to 5 screenshots.
- Captures the page URL, user agent, viewport, and recent browser console errors automatically.
- Idempotency key per form, so a retry after a network failure does not create a duplicate ticket.
- English and Arabic labels.
- Opt-in per panel, so multi-panel hosts choose which panels show the button.

## Requirements

- PHP 8.3+, Laravel 13, Filament 5.
- A running TMS instance with the `POST /api/v1/tickets` endpoint and its queue worker.

## 1. Create a SourceApp token in the TMS

1. In the TMS admin, open **Source Apps** and create one entry per host project (for example `jit-hr`).
2. Use the token action in the table. The plaintext token is shown **once**; copy it.
   The token is created with the `tickets:create` ability only.

Use one token per host project so tokens can be revoked independently.

## 2. Install in the host project

Add the repository to the host's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/a6e6s/filament-tms-tickets" }
]
```

Then require it. The repository is private, so Composer needs GitHub access (a token or `gh auth` configured for Composer):

```bash
composer require a6e6s/filament-tms-tickets:^0.1
```

Local development alternative (path repository pointing at a checkout):

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
# Optional kill switch; the button is hidden when false.
TMS_TICKETS_ENABLED=true
```

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

Multi-panel app (for example `jit-hr`, which has `admin`, `afms`, `cpe`, `cms`, `crm`, `employee`): add the plugin only to the panels that should report tickets, such as `admin` and `crm`. Panels without the plugin show no button.

Every panel that uses the plugin sends tickets with the host's SourceApp token, so all tickets from one host project share one source.

## 5. Verify the deployment

1. Confirm the TMS is reachable from the host server:
   ```bash
   curl -sS -o /dev/null -w "%{http_code}\n" https://tms.example.com/up
   ```
2. Open a panel with the plugin, click **Create ticket**, fill in the form, and submit.
3. Expected result: a success notification with a ticket number, and the ticket appears in the TMS.
4. Check that the TMS queue worker is running. Screenshots are decoded and stored by the TMS queue.

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

- Button missing: check `TMS_TICKETS_ENABLED`, confirm the plugin is listed in that panel's provider, and run `php artisan optimize:clear`.
- Screenshots fail: check the file type (PNG, JPEG, WebP only) and size limits above.
- Console errors not captured: they are recorded only after the plugin's script loads on the page.

## Development

```bash
composer install
vendor/bin/pest
```

Tests use Orchestra Testbench and `Http::fake()`, so they do not contact a real TMS.
