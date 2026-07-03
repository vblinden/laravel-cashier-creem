# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-07-03

### Added

- GitHub Actions workflows for PHPUnit (PHP 8.2–8.4) and Larastan static analysis
- `billable_model` and `http_timeout` settings in the published `config/cashier.php`
- `Subscription::resolvePausedAt()` for consistent pause timestamp handling
- Expanded test coverage for webhooks, redirect signatures, checkout builder, and customer portal

### Changed

- `Billable::subscription()` and `onProduct()` now query the database instead of relying on an in-memory collection
- `paused_at` is preserved across webhook/API syncs instead of resetting to the current time
- `Checkout::metadata()` can no longer overwrite `billable_id` or `billable_type`
- `findBillableByReference()` resolves the default auth guard's provider model instead of hardcoding `users`
- Removed the direct `guzzlehttp/guzzle` dependency (still available transitively via `illuminate/http`)

### Fixed

- Subscription status helpers (`subscribed()`, `onTrial()`, etc.) now work on fresh model instances without eager loading
- `subscription_items.subscription_id` migration now includes a foreign key with cascade delete (new installs)

### Security

- Webhook signature verification is always enforced; requests are rejected when `CREEM_WEBHOOK_SECRET` is missing or invalid
- `findBillableFromMetadata()` allowlists billable types to configured auth provider models and `cashier.billable_model`

### Breaking changes

- **`CREEM_WEBHOOK_SECRET` is required.** Previously, webhooks were accepted without signature verification when the secret was unset. Configure the secret from your Creem dashboard in every environment (including sandbox/local).
- **Billable metadata is allowlisted.** Webhook metadata `billable_type` must match `cashier.billable_model` or a model registered under `auth.providers.*`. If you use a custom billable model that is not an auth provider, set `CASHIER_BILLABLE_MODEL` in `.env`.
- **Custom metadata cannot override billable identifiers.** `->metadata()` no longer allows overwriting `billable_id` or `billable_type` set at checkout.

### Upgrade guide

1. Ensure `CREEM_WEBHOOK_SECRET` is set in `.env` for all environments.
2. If your billable model is not an auth provider, add `CASHIER_BILLABLE_MODEL=App\Models\YourModel::class`.
3. Re-publish config if you want the new `billable_model` and `http_timeout` keys:
   ```bash
   php artisan vendor:publish --tag="cashier-config" --force
   ```
4. **Existing databases** that already ran the `subscription_items` migration will not automatically receive the new foreign key. Add a follow-up migration in your app if you need referential integrity on existing installs.

## [1.0.0] - 2026-07-03

### Added

- Initial release: Creem billing integration for Laravel with subscriptions, one-time checkout, customer portal, and webhook sync

[1.1.0]: https://github.com/vblinden/laravel-cashier-creem/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/vblinden/laravel-cashier-creem/releases/tag/v1.0.0
