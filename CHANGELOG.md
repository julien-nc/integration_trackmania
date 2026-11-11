# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.4.1 – 2026-11-11

### Changed

- Ignore even more files when building the release archive @julien-nc
- Rename screenshots @julien-nc

## 2.4.0 – 2026-11-09

### Changed

- Make the world record table column disabled by default, it is now opt-in @julien-nc
- Get score positions with parallel requests in batches of 8 @julien-nc
- Do not fetch the favorites in the cron job as it does not use them @julien-nc
- Wrap bulk track position DB writes in a transaction @julien-nc
- Switch to IUserConfig @julien-nc
- Remove leftover debug logs and the redundant app context in log calls @julien-nc
- Ignore more files when building the release archive @julien-nc

## 2.3.0 – 2026-11-08

### Added

- Show world record for each track @julien-nc
- Show world record in the map info modal and map image on hover on map name table cells @julien-nc
- Display user skins thumbnails if available and search in the skin zip to find a preview if the API says there is no preview @julien-nc
- Show other's position and add a button to fetch it on demand @julien-nc
- Progressive display of the table and record count while getting data @julien-nc
- Add basic AGENTS.md and improve it @julien-nc [#1](https://github.com/julien-nc/integration_trackmania/pull/1)

### Changed

- Get records with parallel requests in batches of 8 and get favorites in a separate request, better progress reporting @julien-nc
- Run 'other account' requests in parallel in batches of 8 and cache results @julien-nc
- Batch track position DB queries @julien-nc
- Frontend performance optimizations (sorting, filtering and rendering) @julien-nc
- Refactor the settings form, make table header sticky, improve page background and style @julien-nc
- Drop deprecated endpoints in favour of new ones @julien-nc
- Do not cache the connected user's records @julien-nc
- Cleaner date format @julien-nc
- Support for Nextcloud 36, set guzzle timeout to 120 @julien-nc
- Switch to vite 8 with faster rolldown @julien-nc
- Update dependencies and screenshots @julien-nc
- Disable findUnusedIssueHandlerSuppression in psalm config @julien-nc

### Fixed

- Fix flag and display name (service account info does not contain this info about the real account) by using tm.io @julien-nc

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
