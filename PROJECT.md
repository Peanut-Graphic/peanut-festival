# Peanut Festival project starting point

Manage community-festival performers, scheduling, ticketing, voting, volunteers, and vendors in WordPress.

## Direction

Documented intent: Manage community-festival performers, scheduling, ticketing, voting, volunteers, and vendors in WordPress.

Owner confirmation needed: ultimate scope, launch or completion acceptance criteria, and the next priority. This brief does not mark any feature or project complete.

## Source baseline

- Inspected: 2026-10-09
- Local branch: `feat/signed-update-gate`
- Local HEAD: `5b5f208edefd74b91a935657539eb140583c9d1f`
- Lifecycle registry: `maintenance`; classification: `distributed-plugin` (registry reviewed 2026-08-29; current lifecycle not independently confirmed).
- Working tree at inspection: contains local changes.

## What it is built with

- frontend/package.json: react ^18.3.1; tailwindcss ^3.4.14; typescript ^5.6.3; vite ^8.0.3; vitest ^4.1.1
- composer.json: php >=8.0
- peanut-festival.php: WordPress plugin Peanut Festival; WordPress 6.0; PHP 8.0

## Build and verification entry points

These are source-declared entry points, not commands executed by this audit. Install dependencies and configure a local environment according to the linked setup documentation before running them. Release/hosting actions remain subject to the project’s deployment rules.

- frontend/package.json: `npm run dev`
- frontend/package.json: `npm run build`
- frontend/package.json: `npm test`
- frontend/package.json: `npm run lint`
- frontend/package.json: `npm run typecheck`

## Structure and relationships

- `public/` is present in this checkout.
- `tests/` is present in this checkout.
- Cross-project integration details belong in the linked architecture and API documents; folder similarity alone does not establish a dependency.

## Gaps to resolve

- Working tree has uncommitted changes; this baseline includes local declarations beyond the recorded HEAD.
- Owner acceptance criteria and current priority order are not yet recorded in this brief.

## Source documents

- [README.md](README.md)
- [docs/](docs/)

## Keeping this current

Update this starting point when product direction, architecture, dependencies, or build/release entry points change. Recheck source facts against the current checkout. Date stamps describe inspection, not live production verification.

<!-- dino-source-audit {"date":"2026-10-09","head":"5b5f208edefd74b91a935657539eb140583c9d1f","sources":{"README.md":"e108586b1d18b55a21bf96026f4519405473e71673bda752ca6871cc2506be83","composer.json":"1d03753b0e50008e9f373fa3431f8c115198265c52ee0b2a7316c46d4dcb9fd4","frontend/package.json":"ec09a4dd2af9c41fbe38f1f237cdfffefe8f7b16f63457da735c749513f1f73f","peanut-festival.php":"d0e7c305fca1b2e60445b9600a04e8e9f3fe87cd700fbf57ff530f9f17c96d8e"}} -->
