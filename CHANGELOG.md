# Changelog

All notable changes to the Peanut Festival plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.2] - 2026-08-21

### Security
- Refused unsigned or tampered update packages and removed the Firebase
  public-API-key fallback from server-side writes.
- Upgraded the frontend dependency tree to patched releases, including
  `react-router-dom`, and recompiled the tracked assets from that graph.

### Fixed
- Restored the regression harness and guarded upcoming/date-range show queries,
  including fail-closed handling for reversed ranges.
- Improved mobile festival-management surfaces and form accessibility.

### Changed
- Added repository-owned runtime, platform-manifest, release-readiness,
  dependency-audit, MariaDB, real-WordPress, and build-artifact gates.

## [1.3.1] - 2026-07-19

### Security
- Moved Stripe keys out of the codebase and into environment variables.
- Hardened the REST API and the voting system, including SQL-injection
  prevention around votes, and hardened the flyer generator.

### Fixed
- PHP 8.4 implicit-nullable parameter deprecations in the Mailchimp, Performers
  and Flyer paths.
- Search input icon overlap on the Attendees page.

### Changed
- Split the REST API admin surface into focused traits, and competitions into
  separate class files.
- Added performance indexes for the votes and transactions tables.

### Added
- Frontend test coverage to roughly 80%, including Shows, Venues and ShowForm
  component suites, plus security tests for the voting system.
- Security, database and developer guides.


### Added
- Added `get_upcoming()` and inclusive `get_by_date_range()` show queries, including fail-closed handling for reversed ranges.

### Fixed

- Reconciled the PHP 8.0 host floor with an exact PHP 8.1 development lock and executable CI contract checks.
- Restored the standalone PHPUnit harness with faithful WordPress authentication, integer, option, and mail mocks.
- Isolated Firebase singleton state between tests and removed obsolete PHP 8.5 reflection calls.
- Replaced notification fallback and skipped show-query placeholders with behavioral regression tests.
- Paired Vite 8 with its supported React plugin and repaired frontend lint, unit, and accessibility gates.
- Improved keyboard and mobile accessibility for navigation, modals, form controls, and toast notifications.
- Corrected the CI build-artifact path and made an empty upload fail closed.

## [1.3.0] - 2026-01-01

### Added

#### Full Double Elimination Tournament Brackets
- Complete double elimination bracket generation with winners and losers brackets
- Grand finals with automatic reset if losers bracket winner beats winners bracket winner
- Proper loser advancement from winners bracket to losers bracket
- Bracket positions using format: W_R{round}M{match}, L_R{round}M{match}, GF, GFR
- New `bracket_type` field: winners, losers, grand_finals, grand_finals_reset
- Tracks losers with `loser_id` field for proper bracket flow
- Database migration 1.4.0 for new schema columns

#### Environment Variable Support for Credentials
- Sensitive settings now check environment variables first
- Supported env vars: Firebase, Stripe, Eventbrite, Mailchimp, Booker credentials
- Added `.env.example` template with all supported variables
- Base64 encoding support for Firebase service account JSON

#### CI/CD Pipeline
- Added GitHub Actions workflow for automated testing
- PHP linting and PHPUnit tests
- Frontend TypeScript checking, linting, and Vitest tests
- Automatic build artifact upload

### Changed
- `get_bracket()` API returns separate `winners_bracket`, `losers_bracket`, `grand_finals`, `grand_finals_reset` for double elimination
- `complete_match()` now determines and stores loser_id for bracket advancement
- `advance_winner()` routes to competition-type-specific advancement logic
- `Peanut_Festival_Settings::get()` checks environment variables for sensitive keys

### Security
- Firebase security rules now role-based (admin, service_account claims required for writes)
- User votes restricted to own user ID in Firebase
- Leaderboard writes restricted to service account only
- Added rate limiting to Firebase subscribe endpoint
- Fixed unescaped output in volunteer signup and leaderboard templates

## [1.2.10] - 2024-12-30

### Fixed
- Switched from fullscreen mode to embedded WordPress admin mode - WordPress sidebar navigation now visible
- Simplified Layout component with proper flex layout (flex-shrink-0 sidebar, flex-1 content)
- Reduced sidebar width from 64 to 56 for better fit within WordPress admin
- Fixed stat cards and tables displaying correctly within WordPress admin content area

## [1.2.6] - 2024-12-30

### Fixed
- Dashboard stat cards not displaying in 4-column grid - added CSS isolation for Tailwind grid classes
- Tables cut off on right side - added w-full to Layout root and overflow handling
- Complete CSS isolation for Tailwind flex, grid, width, and overflow utilities
- Added !important overrides for responsive breakpoints (md:grid-cols-2, lg:grid-cols-4)

## [1.2.5] - 2024-12-30

### Fixed
- Admin layout conflict with WordPress admin menu - now uses fullscreen mode like Peanut Suite
- Added proper fullscreen container with position:fixed wrapper
- Hide WordPress admin chrome (toolbar, menu, footer) when viewing Festival admin
- CSS isolation for Tailwind classes to prevent WordPress style conflicts
- Proper button, input, and link style resets inside the React app

## [1.2.4] - 2024-12-30

### Fixed
- Admin table columns (Status, Actions) cut off on Festivals, Performers, and Volunteers pages
- Added proper overflow-x-auto wrapper and min-width constraints to tables for horizontal scrolling

## [1.2.0] - 2024-12-30

### Added

#### Firebase Real-Time Integration
- Firebase Realtime Database integration for live updates
- OAuth2 JWT token generation for Firebase authentication (no Composer dependencies)
- Real-time sync for votes, matches, shows, and performers
- Debounced and batched sync operations for efficiency
- WordPress hooks integration for automatic data sync

#### Push Notifications (Firebase Cloud Messaging)
- Push notification support via FCM
- Topic-based subscription for festivals
- Notification types: voting starting, performer on stage, winner announced
- In-app notification toasts for foreground messages
- Service worker for background push handling

#### Progressive Web App (PWA) Support
- Web app manifest for installable PWA
- Service worker with offline caching strategy
- Static asset caching for CSS, JS, and images
- API response caching for offline schedule viewing
- Background sync for vote submissions when offline
- PWA shortcuts for schedule and voting

#### Admin Firebase Settings
- Firebase configuration UI in Settings page
- Service account credentials upload
- Test connection functionality
- Manual festival sync to Firebase
- Send push notifications from admin

#### Live Voting Display Shortcodes
- `[pf_bracket]` - Interactive tournament bracket display with live updates
- `[pf_live_votes]` - Real-time vote counter with bar, number, and pie chart styles
- `[pf_leaderboard]` - Live performer leaderboard with podium display
- `[pf_winner]` - Animated winner announcement with confetti celebration

#### Vote Verification System
- Rate limiting (configurable votes per minute per IP)
- Email verification mode with one-time codes
- One-vote-per-email enforcement option
- Device fingerprinting for fraud prevention
- Comprehensive vote validation API

#### Public API Endpoints
- `/peanut-festival/v1/leaderboard` - Get performer rankings
- `/peanut-festival/v1/matches/{id}/votes` - Get live match vote counts

#### Frontend Assets
- Bracket display CSS with responsive design
- Live votes CSS with multiple visualization styles
- Leaderboard CSS with animated podium
- Winner announcement CSS with particle effects
- JavaScript for all live-updating components

## [1.1.0] - 2024-12-30

### Added

#### Peanut Booker Integration
- Bidirectional performer sync between Festival and Booker
- Automatic performer linking by email or WordPress user ID
- Booker achievement badges displayed on Festival profiles
- Booker ratings and completed bookings shown on performer cards
- Calendar conflict detection with Booker availability
- REST API endpoints for managing performer links
- Settings page for integration configuration

#### Competition/Bracket System
- Tournament bracket support (single elimination, double elimination, round robin)
- Head-to-head voting for bracket matches
- Automatic bracket generation with proper seeding
- Bye handling for odd participant counts
- Match scheduling with voting windows
- Real-time vote counting
- Winner advancement through bracket rounds
- Round robin standings calculation
- REST API for competition management
- Cron job for auto-completing expired matches

#### Hooks for External Integration
- `peanut_festival_performer_accepted` - Fires when performer is accepted
- `peanut_festival_show_completed` - Fires when show completes
- `peanut_festival_vote_winner` - Fires when voting winner declared
- `peanut_festival_performer_rating` - Fires when performer receives rating
- `peanut_festival_show_scheduled` - Fires when performers assigned to show

#### Show Management Enhancements
- `complete()` method to mark shows as completed
- `schedule_performers()` for batch performer assignment
- `has_schedule_conflict()` for availability checking across platforms

### Changed
- Database schema version bumped to 1.3.0
- Plugin version bumped to 1.1.0

### Database Tables Added
- `pf_booker_links` - Links between Festival and Booker performers
- `pf_competitions` - Tournament/competition definitions
- `pf_competition_matches` - Individual bracket matches

## [1.0.0] - 2024-12-27

### Added

#### Core Plugin
- WordPress plugin architecture with activation/deactivation hooks
- Custom database schema with 23 tables
- Database migration system with version tracking
- REST API with public and admin endpoints
- Settings management with active festival selection

#### Admin Dashboard (React + TypeScript)
- Festival CRUD management
- Shows, Performers, Venues management
- Volunteer shift scheduling with calendar view
- Vendor and Sponsor management
- Voting administration with Borda count system
- Box office with QR code check-in
- Messaging system with broadcast emails
- Analytics dashboard with financial overview
- Reports page with charts and CSV export
- Flyer generator with customizable templates

#### Public Shortcodes
- `[pf_vote]` - Audience voting widget
- `[pf_results]` - Voting results display
- `[pf_schedule]` - Event schedule with filters
- `[pf_flyer]` - Interactive flyer generator
- `[pf_performer_apply]` - Performer application form
- `[pf_volunteer_signup]` - Volunteer registration
- `[pf_vendor_apply]` - Vendor application form
- `[pf_checkin]` - QR code check-in for staff

#### Integrations
- Stripe payment processing for tickets and vendor fees
- Eventbrite event synchronization
- Mailchimp audience sync and campaigns
- Email notifications system

#### Security & Performance
- SQL injection prevention with parameterized queries
- Input sanitization on all endpoints
- CSRF protection via WordPress nonces
- Rate limiting on public API endpoints
- Object caching with group-based invalidation
- Background job queue for async email processing
- Structured logging with sensitive data redaction

#### Quality
- PHPUnit test suite (75 tests)
- Vitest test suite (72 tests)
- OpenAPI 3.0 documentation for REST API
- PHPDoc documentation on core classes
- Mobile-responsive design
- Accessibility support (ARIA labels, keyboard navigation)

### Database Tables
- `pf_festivals` - Festival definitions
- `pf_shows` - Show/event scheduling
- `pf_venues` - Venue information
- `pf_performers` - Performer profiles
- `pf_show_performers` - Show-performer assignments
- `pf_volunteers` - Volunteer registrations
- `pf_shifts` - Volunteer shift definitions
- `pf_volunteer_shifts` - Volunteer-shift assignments
- `pf_vendors` - Vendor applications
- `pf_sponsors` - Sponsor information
- `pf_attendees` - Attendee records
- `pf_tickets` - Ticket purchases
- `pf_coupons` - Discount codes
- `pf_votes` - Individual votes
- `pf_voting_config` - Voting settings per festival
- `pf_messages` - Broadcast messages
- `pf_transactions` - Financial transactions
- `pf_check_ins` - QR code check-in events
- `pf_email_logs` - Email send history
- `pf_settings` - Plugin settings
- `pf_job_queue` - Background job queue

[1.2.0]: https://github.com/peanutgraphic/peanut-festival/releases/tag/v1.2.0
[1.1.0]: https://github.com/peanutgraphic/peanut-festival/releases/tag/v1.1.0
[1.0.0]: https://github.com/peanutgraphic/peanut-festival/releases/tag/v1.0.0
