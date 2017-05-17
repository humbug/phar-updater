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

### Fixed
- Version strings using the `v` prefix are now correctly normalised before
comparisons.
