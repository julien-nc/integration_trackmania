# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.2.0 – 2026-08-23

### Changed

- Update npm and composer dependencies
- Fix psalm issues

## 2.1.0 – 2026-07-18

### Added

- Support for Nextcloud 35 @julien-nc
- Display last load date and last loading time @julien-nc

### Changed

- Support for Nextcloud 34, drop support for NC < 34 @julien-nc
- Bump min PHP version to 8.3 @julien-nc

### Fixed

- Fix login logic, old one is not working anymore @julien-nc
- Fix map details modal when there is no mapper name @julien-nc
- Fix title margin @julien-nc

## 2.0.1 – 2025-10-16

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
