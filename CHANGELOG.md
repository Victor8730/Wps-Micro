# Changelog

All notable changes to WPS Micro are documented in this file.

The project follows [Semantic Versioning](https://semver.org/).

## [3.1.0] - 2026-09-15

### Added

- Compiled static and dynamic route indexes for faster request matching.
- Named routes with PHP and Twig URL generation.
- Nested route groups with shared prefixes, name prefixes, and middleware.
- Custom route constraints plus numeric and UUID constraint helpers.
- `route:list` console command for inspecting application routes.
- Reusable and inline custom validation rules.
- Numeric `min` and `max` validation for integer and numeric fields.
- PHPStan level 8, PHP CS Fixer, and an enforced Clover coverage threshold.

### Changed

- CI now verifies static analysis, code style, and test coverage on PHP 8.3.
- Container reflection handles union types containing intersection candidates.
- Framework array contracts are documented for static analysis.
- Duplicate route and name checks use indexes instead of scanning all routes.

### Fixed

- Middleware instances retain their identity in route definitions and groups.
- Named validation rules no longer collide with callable PHP function names.
- Route constraints consistently match decoded Unicode and encoded parameters,
  without treating encoded slashes as literal route separators.
- Segment-based route matching preserves parameter boundaries when multiple
  parameters accept encoded slashes. Only a final standalone parameter may
  consume additional unencoded path segments.
- Numeric bounds preserve precision for integer, decimal, and exponent-form
  strings without expanding exponents into large buffers.
- Numeric rules reject non-finite values and exponents outside the safe integer range.
- Empty route groups no longer trigger an array-offset deprecation on PHP 8.5.

## [3.0.0] - 2026-07-22

### Added

- Standalone Composer library structure under `src/`.
- `WpsMicro\Core\` namespace for all framework APIs.
- Application-level overrides for default kernel service bindings.
- Fail-fast checks for configuration and route files.
- Native PHP 8.3 types across the public framework API.
- MariaDB migration and rollback coverage in GitHub Actions.
- Dedicated upgrade and security documentation.

### Changed

- Framework core and application skeleton are maintained as separate packages.
- Controller actions must return a `Response` instance.
- Custom 404 rendering failures are handled and logged as server errors.
- Validation rule definitions are checked even when input values are missing.
- Generator commands receive application paths and namespaces explicitly.
- Composer uses the standard root-level `vendor/` directory.

### Removed

- Application controllers, models, services, routes, migrations, and templates.
- Frontend assets, Vite configuration, and deployment files from the core package.
- Legacy controller string returns and output-buffer response handling.
- Convention-based controller discovery.

## [2.1.0] - 2026-07-18

- Final monolithic release containing both framework and application code.
- Hardened request handling, response headers, routing, sessions, validation,
  migrations, authentication examples, Vite, Tailwind CSS, and Docker support.

Applications starting with v3 should use the separate
`webpagestudio/wps-micro-skeleton` package.
