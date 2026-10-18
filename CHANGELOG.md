# Changelog

All notable changes to `ttpryg/config` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_No unreleased changes yet._

## [1.2.0] - 2026-10-16

### Changed

- Enhanced the PDO database driver with an atomic upsert (driver-aware
  `INSERT ... ON DUPLICATE KEY UPDATE` / `ON CONFLICT ... DO UPDATE`) and improved
  error handling when persisting configuration values.

## [1.1.0] - 2026-09-21

### Added

- Optional value encryption: configuration values persisted to the database can be
  encrypted with OpenSSL AES-256-GCM via `ConfigEncryptor`, while remaining plain
  in memory.

## [1.0.0] - 2026-09-15

### Added

- Initial release: `ConfigManager` / `ConfigRepository` with dot-notation access
  (`$config->get('database.connections.mysql.host')`) and `ArrayAccess` support.
- Directory auto-loader that loads every `.php` config file into key-partitioned arrays.
- Database storage and persistence through `PdoDatabaseDriver` (and a
  `DatabaseDriverInterface` for custom drivers), with zero external runtime dependencies.

### Changed

- Renamed the package from `ttpryg/config-manager` to `ttpryg/config` and updated
  namespaces to `Ttpryg\Config`.
- Added CI/CD and code-quality tooling (Rector, Pint).

[Unreleased]: https://github.com/ttpryg/config/compare/1.2.0...HEAD
[1.2.0]: https://github.com/ttpryg/config/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/ttpryg/config/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/ttpryg/config/releases/tag/1.0.0
