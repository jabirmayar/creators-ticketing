# Changelog

## v1.3.0 - 2026-10-23

- Add opt-in public support page, anonymous ticket submission, and private tracking/chat links.
- Add opt-in queued customer confirmation and public staff-reply emails with browser reply links.
- Preserve internal notes and private attachment authorization; do not email customer-authored replies.
- Recheck chat authorization on every request, lock access credentials, enforce closed-ticket restrictions, and rate-limit submissions and replies.
- Validate active public departments and attached forms against server definitions, and escape customer chat input.
- Dispatch ticket creation once and allow agents to mark guest replies as seen.
- Add guest migrations that preserve requester ID types and foreign-key actions and guard rollback against orphaned guest data.
- Include host-application regression tests for default routing, guest and authenticated flows, mail dispatch, access isolation, and upgrades with integer, UUID, ULID, and custom user IDs.

Run migrations, republish Filament assets, and clear cached configuration/routes when upgrading. Read the README upgrade notes for validation and custom Livewire integration changes. Inbound email replies are not supported.
