# Peanut Festival

A comprehensive WordPress plugin for managing community festivals, including performer management, scheduling, ticketing, voting, volunteers, and vendors.

## Features

### Admin Dashboard
- **Festival Management** - Create and manage multiple festivals with dates, venues, and settings
- **Performer Management** - Track performers, their shows, and technical requirements
- **Show Scheduling** - Visual calendar for scheduling performances across venues
- **Ticketing & Box Office** - Manage tickets, attendees, coupons, and QR code check-ins
- **Voting System** - Public voting with Borda count for audience choice awards
- **Volunteer Management** - Volunteer signup, shift scheduling, and assignments
- **Vendor Applications** - Accept and manage vendor booth applications
- **Sponsor Management** - Track sponsors and sponsorship tiers
- **Messaging** - Broadcast emails to performers, volunteers, and attendees
- **Analytics** - Financial overview, attendance stats, and exportable reports
- **Flyer Generator** - Canvas-based promotional flyer creation

### Competition System
- **Tournament Brackets** - Single/double elimination and round robin
- **Head-to-Head Voting** - Live voting for bracket matches
- **Automatic Advancement** - Winners advance through bracket rounds
- **Live Vote Display** - Real-time vote counters with multiple styles

### Real-Time Features (Firebase)
- **Live Updates** - Real-time vote counts and leaderboards via Firebase
- **Push Notifications** - FCM notifications for voting, performances, winners
- **PWA Support** - Installable web app with offline schedule caching
- **Background Sync** - Vote submission when back online

### Integrations
- **Stripe** - Payment processing for tickets and vendor fees
- **Eventbrite** - Bi-directional event synchronization
- **Mailchimp** - Audience sync and newsletter campaigns
- **Peanut Booker** - Performer profile sync and calendar integration
- **Firebase** - Real-time database and push notifications

## Requirements

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+

## Installation

1. Upload the `peanut-festival` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Navigate to **Peanut Festival** in the admin menu to begin setup

## Quick Start

1. Go to **Peanut Festival > Settings** and configure your organization details
2. Create your first festival under **Peanut Festival > Festivals**
3. Add venues under **Peanut Festival > Venues**
4. Add performers and create shows
5. Use shortcodes to display public-facing content

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[pf_vote]` | Public voting widget |
| `[pf_results]` | Display voting results |
| `[pf_schedule]` | Festival schedule/lineup display |
| `[pf_performer_apply]` | Performer application form |
| `[pf_volunteer_signup]` | Volunteer signup form |
| `[pf_vendor_apply]` | Vendor application form |
| `[pf_flyer]` | Interactive flyer generator |
| `[pf_checkin]` | Staff QR code check-in interface |
| `[pf_bracket]` | Tournament bracket display (live updating) |
| `[pf_live_votes]` | Real-time vote counter (bar, number, or pie style) |
| `[pf_leaderboard]` | Live performer leaderboard with podium |
| `[pf_winner]` | Animated winner announcement with confetti |

All shortcodes accept a `festival_id` attribute to specify which festival to display.

### Live Display Shortcodes

```html
<!-- Tournament bracket with Firebase real-time updates -->
[pf_bracket competition_id="1" theme="dark"]

<!-- Live vote counter with different display styles -->
[pf_live_votes show_id="5" style="bar" show_percentage="true"]
[pf_live_votes show_id="5" style="pie" animated="true"]

<!-- Performer leaderboard -->
[pf_leaderboard festival_id="1" limit="10" show_photos="true"]

<!-- Winner announcement (auto-reveals when voting closes) -->
[pf_winner show_id="5" confetti="true"]
```

## User Roles

The plugin creates custom roles with specific capabilities:

- **Festival Producer** - Full access to all festival features
- **Venue Manager** - Manage venues and show scheduling
- **Volunteer Coordinator** - Manage volunteers and shifts
- **Box Office** - Check-in attendees and manage tickets

## Development

### Prerequisites

- PHP 8.1+ for Composer dependencies and test tooling
- Composer
- Node.js 22.22.2
- npm 10.9.7 (use nvm use from the repository root)

### Setup

```bash
# Clone the repository
git clone https://github.com/peanutgraphic/peanut-festival.git
cd peanut-festival

# Install PHP dependencies
composer install

# Select the exact frontend runtime and install locked dependencies
nvm use
cd frontend
npm ci

# Build frontend assets
npm run build

# Start development server
npm run dev
```

### Local Development with wp-env

```bash
# Start WordPress environment
npx wp-env start

# Access the site at http://localhost:8888
# Admin: http://localhost:8888/wp-admin (admin/password)
```

### Testing

```bash
# Run PHP tests
./vendor/bin/phpunit

# Run frontend tests
cd frontend
npm run test

# Run frontend tests once
npm run test:run

# Type checking
npm run typecheck
```

### Building for Production

```bash
cd frontend
npm run build
```

Build artifacts are output to `assets/dist/`.

## API Documentation

The plugin provides a REST API for both public and admin operations:

- **Public API**: `docs/openapi.yaml` - Events, voting, applications, payments
- **Admin API**: `docs/openapi-admin.yaml` - Full CRUD, messaging, integrations

### Example Endpoints

```
GET  /wp-json/peanut-festival/v1/events
POST /wp-json/peanut-festival/v1/vote
POST /wp-json/peanut-festival/v1/apply/performer
GET  /wp-json/peanut-festival/v1/admin/shows
GET  /wp-json/peanut-festival/v1/leaderboard
GET  /wp-json/peanut-festival/v1/firebase/config
GET  /wp-json/peanut-festival/v1/competitions/{id}/bracket
POST /wp-json/peanut-festival/v1/matches/{id}/vote
```

## Architecture

```
peanut-festival/
├── includes/           # PHP classes
│   ├── class-database.php
│   ├── class-rest-api.php
│   ├── class-performers.php
│   ├── class-shows.php
│   ├── class-volunteers.php
│   ├── class-vendors.php
│   ├── class-voting.php
│   ├── class-cache.php
│   ├── class-job-queue.php
│   └── ...
├── frontend/           # React admin dashboard
│   └── src/
│       ├── components/
│       ├── pages/
│       └── api/
├── public/             # Public-facing PHP
│   ├── class-public.php
│   └── templates/
├── admin/              # Admin PHP
├── assets/             # Compiled assets
├── tests/              # PHPUnit & Vitest tests
└── docs/               # API documentation
```

## Database

The plugin creates 23 custom tables with the `pf_` prefix:

- `pf_festivals`, `pf_shows`, `pf_venues`
- `pf_performers`, `pf_show_performers`
- `pf_volunteers`, `pf_shifts`, `pf_volunteer_shifts`
- `pf_vendors`, `pf_sponsors`
- `pf_attendees`, `pf_tickets`, `pf_coupons`
- `pf_votes`, `pf_voting_config`
- `pf_messages`, `pf_transactions`
- `pf_check_ins`, `pf_email_logs`
- `pf_settings`, `pf_job_queue`

Migrations are handled automatically via `class-migrations.php`.

## Security

- SQL injection prevention with parameterized queries
- Input sanitization on all endpoints
- CSRF protection via WordPress nonces
- Rate limiting on public API endpoints
- Sensitive data redaction in logs

## License

Proprietary - Peanut Graphic

## Support

For issues and feature requests, please [open an issue](https://github.com/peanutgraphic/peanut-festival/issues).
