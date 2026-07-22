# Changelog

All notable changes to WPS Micro are documented in this file.

The project follows [Semantic Versioning](https://semver.org/).

## [3.0.0] - Unreleased

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
