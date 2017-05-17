# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- Support for Manifest files enabling update restrictions based on PHP
version and update notes for end-user display.
- A SHA256 strategy has been added. 

### Changed
- `VersionParser` now uses `Composer\Semver` for version comparison/sorting.
- Github strategy will not update to the next major version by default (e.g.
`1.2` to `2.0`).
- Version strings containing git metadata, and which do not follow Semver,
will be treated as development `dev` versions. Updating across `dev` versions
should use the SHA256 strategy which is not tied to versions.
- Dependency on `padraic\humbug_get_contents` has been removed.
- Minimum PHP version supported moved to `5.6`.

### Fixed
- Version strings using the `v` prefix are now correctly normalised before
comparisons.

## [1.0.3] - 2016-01-06

### Fixed
- Remove unnecessary `require` dependency for `symfony\finder` in composer.json.

## [1.0.2] - 2015-05-29

### Added
- Support parsing of versions with non-standard git metadata (e.g. `1.0.0-23-ggh5h79`).
These are not Semver compliant and required additional support.

### Fixed
- Fixed a method call error in `GithubStrategy` class.

## [1.0.1] - 2015-05-28

- Minimum PHP version supported moved to `5.3`.

## [1.0.0] - 2015-05-25

- Initial release
