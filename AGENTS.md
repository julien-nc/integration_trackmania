# AGENTS.md

Nextcloud app (PHP 8.3+ backend, Vue 3 frontend) for Trackmania integration. Targets NC 34-35.

## What this app is

This app only does one thing: It allows the user to connect as a "service account"
and display all the Trackmania track records in a dynamic table in a dedicated page.

## Build & Verification

**Frontend** (Vite with ESLint/Stylelint plugins):
- `npm run build` - production build
- `npm run watch` - dev watch mode
- `npm run lint` / `npm run lint:fix`
- `npm run stylelint` / `npm run stylelint:fix`

**Backend** (Composer):
- `composer run cs:check` / `composer run cs:fix` - PHP-CS-Fixer
- `composer run psalm` - static analysis (uses vendor-bin)

**Makefile shortcuts**:
- `make build` - runs composer + npm build
- `make dev` - runs composer + npm dev

**Pre-commit**: Run `composer run cs:check`, `composer run psalm`, and `npm run build`. ESLint/Stylelint are integrated into Vite build.

## Architecture

**PHP** (`lib/`, namespace `OCA\Trackmania`):
- Controllers: `ConfigController`, `PageController`, `TrackmaniaAPIController`
- Services: `TrackmaniaAPIService`, `SecretService`
- Routes: `appinfo/routes.php`
- Background jobs & commands: registered in `appinfo/info.xml`

**Frontend** (`src/`, Vue 3):
- 3 entry points: `main.js`, `personalSettings.js`, `adminSettings.js`
- Templates: `templates/main.php`, `templates/personalSettings.php`, `templates/adminSettings.php`

## Gotchas

- **Built assets must not be committed**
- 
