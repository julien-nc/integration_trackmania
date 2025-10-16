# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.0.0 – 2025-10-16

### Added

- Optional oauth credentials to get and display the map author names (convert from user IDs)
- Store positions on each refresh
- Show best position and last seen
- Background job (twice a day) and occ command to update positions

### Changed

- Improve raw map records speed, convert some service methods to generators
- Use Vue 3 and nextcloud/vue 9
- Drop support for NC < 32
- Add support for NC 33

## 1.0.1 – 2024-09-01

### Changed

- Improve logic to get map thumbnails

### Fixed

- Adjust to changes in TM core API, mostly to get map record

## 1.0.0 – 2024-04-07

### Added

* the app
