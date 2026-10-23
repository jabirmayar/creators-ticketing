# Host application regression tests

These integration tests run inside a Laravel development application that installs this package, Livewire, Pest 3, and pest-plugin-laravel. The host must provide its usual `Tests\TestCase`, a `User::factory()`, and a Pest binding of `Tests\TestCase` to `tests/Feature` (the Laravel Pest starter supplies these).

Copy the three files in `tests/Feature` into the host application's `tests/Feature` directory. Configure the host's `phpunit.xml` for `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, and `MAIL_MAILER=array`. Ensure the SQLite PDO extension is enabled. Leave `TICKETING_PUBLIC_PAGE_ENABLED` unset for the default-routing tests; the guest tests explicitly enable the route.

Run from the host application's root:

```bash
php artisan test --filter=CreatorsTicketing
```

All database tests use `RefreshDatabase` against the configured test database. Never point them at production data. Notifications are faked except for the queue-dispatch check and simulated transport-failure check; no real customer emails are sent.

The upgrade tests cover integer, UUID, ULID, and custom string/char IDs, existing data preservation, foreign-key preservation, and guarded rollback on SQLite. MySQL/MariaDB and PostgreSQL migration branches still require database-specific verification. This suite does not constitute a matrix test of every supported Laravel, Filament, and Livewire version.
