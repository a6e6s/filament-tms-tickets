# Security Policy

## Reporting a vulnerability

Please do not open a public issue for security problems. Use GitHub's private vulnerability reporting instead:
<https://github.com/a6e6s/filament-tms-tickets/security/advisories/new>

Include the plugin version, the Laravel and Filament versions, and steps to reproduce. You can expect an acknowledgement within a few business days.

## Operational notes for hosts

- The SourceApp token is a bearer secret. It can create tickets but cannot read them. Store it only in `.env`, give each host its own token, and revoke it in the TMS if it leaks.
- Tickets can include page URLs, browser user agents, console errors, and screenshots. Those can contain personal data or on-screen secrets, so limit who can use the plugin and review what users capture.
- The plain fallback form at `/tms-tickets/report` requires an authenticated user by default. Keep it that way unless you add rate limiting.
- The plugin sends captured console errors and metadata as they are; it does not redact them. Console messages can contain tokens or personal data logged by your app, so avoid logging secrets to the browser console.
