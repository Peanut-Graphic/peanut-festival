# Database Schema Documentation

## Overview

Peanut Festival uses a comprehensive multi-table database schema to manage festivals, shows, performers, voting, ticketing, volunteers, vendors, sponsors, and financial tracking.

## Table Prefix

All tables use the WordPress table prefix (typically `wp_`) followed by `pf_`.

## Entity Relationship Diagram

```
                    ┌─────────────────┐
                    │  pf_festivals   │
                    └────────┬────────┘
                             │1:N
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│    pf_shows     │ │  pf_performers  │ │   pf_venues     │
└────────┬────────┘ └────────┬────────┘ └─────────────────┘
         │                   │
         │N:M                │
         ▼                   │
┌──────────────────────┐     │
│  pf_show_performers  │◄────┘
└──────────────────────┘

┌─────────────────┐       ┌──────────────────┐
│    pf_shows     │───1:N─│    pf_votes      │
└─────────────────┘       └──────────────────┘

┌─────────────────┐       ┌──────────────────┐
│  pf_volunteers  │───N:M─│pf_volunteer_shifts│
└─────────────────┘       └──────────────────┘
         │                         │
         └─────────┬───────────────┘
                   ▼
         ┌──────────────────────────┐
         │pf_volunteer_assignments  │
         └──────────────────────────┘

┌─────────────────┐       ┌──────────────────┐
│  pf_attendees   │───1:N─│   pf_tickets     │
└─────────────────┘       └──────────────────┘

┌─────────────────┐       ┌──────────────────────┐
│ pf_competitions │───1:N─│pf_competition_matches│
└─────────────────┘       └──────────────────────┘
```

---

## Core Tables

### pf_festivals

Primary table for festival definitions and settings.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `name` | VARCHAR(255) | Festival name |
| `slug` | VARCHAR(255) | Unique URL slug |
| `description` | TEXT | Festival description |
| `start_date` | DATE | First day of festival |
| `end_date` | DATE | Last day of festival |
| `location` | VARCHAR(255) | Primary location |
| `status` | VARCHAR(20) | 'draft', 'active', 'archived' |
| `settings` | LONGTEXT | JSON configuration |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `unique_slug` (slug)
- `idx_status` (status)
- `idx_dates` (start_date, end_date)

---

### pf_shows

Individual shows/performances within a festival.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `eventbrite_id` | VARCHAR(50) | Eventbrite event ID |
| `title` | VARCHAR(255) | Show title |
| `slug` | VARCHAR(255) | URL slug |
| `description` | TEXT | Show description |
| `venue_id` | BIGINT UNSIGNED | FK to venues |
| `show_date` | DATE | Performance date |
| `start_time` | TIME | Start time |
| `end_time` | TIME | End time |
| `capacity` | INT | Maximum attendees |
| `ticket_price` | DECIMAL(10,2) | Ticket price |
| `status` | VARCHAR(20) | 'draft', 'published', 'cancelled' |
| `featured` | TINYINT(1) | Featured show flag |
| `kid_friendly` | TINYINT(1) | Family friendly flag |
| `voting_config` | LONGTEXT | JSON voting settings |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_venue_id` (venue_id)
- `idx_show_date` (show_date)
- `idx_status` (status)

---

### pf_performers

Performer profiles for festivals.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `booker_link_id` | BIGINT UNSIGNED | FK to Booker integration |
| `user_id` | BIGINT UNSIGNED | WordPress user ID |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `name` | VARCHAR(255) | Performer name |
| `email` | VARCHAR(255) | Contact email |
| `phone` | VARCHAR(50) | Contact phone |
| `bio` | TEXT | Performer biography |
| `photo_url` | VARCHAR(500) | Headshot URL |
| `website` | VARCHAR(500) | Website URL |
| `social_links` | LONGTEXT | JSON social media links |
| `performance_type` | VARCHAR(100) | Type of act |
| `technical_requirements` | TEXT | Tech rider |
| `compensation` | DECIMAL(10,2) | Payment amount |
| `travel_covered` | TINYINT(1) | Travel paid flag |
| `lodging_covered` | TINYINT(1) | Lodging paid flag |
| `application_status` | VARCHAR(20) | 'pending', 'approved', 'rejected' |
| `application_date` | DATETIME | When applied |
| `review_notes` | TEXT | Internal notes |
| `reviewed_by` | BIGINT UNSIGNED | Admin user ID |
| `reviewed_at` | DATETIME | Review timestamp |
| `notification_sent` | TINYINT(1) | Notified of decision |
| `rating_internal` | DECIMAL(3,2) | Internal rating |
| `pros` | TEXT | Internal pros |
| `cons` | TEXT | Internal cons |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_application_status` (application_status)
- `idx_user_id` (user_id)
- `idx_booker_link_id` (booker_link_id)

---

### pf_show_performers

Junction table linking performers to shows.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `show_id` | BIGINT UNSIGNED | FK to shows |
| `performer_id` | BIGINT UNSIGNED | FK to performers |
| `slot_order` | INT | Performance order |
| `set_length_minutes` | INT | Set duration |
| `performance_time` | TIME | Scheduled time |
| `confirmed` | TINYINT(1) | Performer confirmed |
| `notes` | TEXT | Additional notes |

**Indexes:**
- `unique_show_performer` (show_id, performer_id)
- `idx_show_id` (show_id)
- `idx_performer_id` (performer_id)

---

### pf_venues

Festival venue management.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `name` | VARCHAR(255) | Venue name |
| `slug` | VARCHAR(255) | URL slug |
| `address` | VARCHAR(500) | Street address |
| `city` | VARCHAR(100) | City |
| `state` | VARCHAR(50) | State |
| `zip` | VARCHAR(20) | Postal code |
| `capacity` | INT | Maximum capacity |
| `venue_type` | VARCHAR(50) | 'theater', 'bar', 'outdoor', etc. |
| `amenities` | LONGTEXT | JSON amenities list |
| `contact_name` | VARCHAR(255) | Venue contact person |
| `contact_email` | VARCHAR(255) | Contact email |
| `contact_phone` | VARCHAR(50) | Contact phone |
| `rental_cost` | DECIMAL(10,2) | Rental fee |
| `revenue_share` | DECIMAL(5,2) | Revenue share % |
| `tech_specs` | TEXT | Technical specifications |
| `pros` | TEXT | Internal pros |
| `cons` | TEXT | Internal cons |
| `rating_internal` | DECIMAL(3,2) | Internal rating |
| `status` | VARCHAR(20) | 'active', 'inactive' |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_venue_type` (venue_type)
- `idx_status` (status)

---

## Voting Tables

### pf_votes

Individual audience votes.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `show_slug` | VARCHAR(200) | Show identifier |
| `group_name` | VARCHAR(50) | Voting group |
| `performer_id` | BIGINT UNSIGNED | FK to performers |
| `vote_rank` | TINYINT(2) | 1 = first place, 2 = second |
| `ip_hash` | VARCHAR(64) | MD5 hash of IP (fraud prevention) |
| `ua_hash` | VARCHAR(64) | User agent hash |
| `token` | VARCHAR(64) | Anonymous voter token |
| `ballot_key` | CHAR(64) NULL | HMAC of show, group and voter IP hash; NULL for administrator test votes (migration 1.7.2) |
| `voted_at` | DATETIME | Vote timestamp |

**Indexes:**
- `idx_show_group` (show_slug, group_name)
- `idx_show_performer` (show_slug, performer_id)
- `idx_ip_hash` (ip_hash)
- `idx_token` (token)
- `ballot_rank` UNIQUE (ballot_key, vote_rank) — one ballot per voter per show group

---

### pf_voting_finals

Calculated final results per show.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `show_slug` | VARCHAR(200) | Show identifier |
| `performer_id` | BIGINT UNSIGNED | FK to performers |
| `group_name` | VARCHAR(50) | Voting group |
| `raw_score` | DECIMAL(10,2) | Raw vote score |
| `normalized_score` | DECIMAL(10,2) | Normalized score |
| `final_rank` | INT | Final placement |
| `first_place_votes` | INT | Count of #1 votes |
| `second_place_votes` | INT | Count of #2 votes |
| `total_votes` | INT | Total vote count |
| `calculated_at` | DATETIME | When calculated |

**Indexes:**
- `idx_show_slug` (show_slug)
- `idx_final_rank` (final_rank)

---

## Competition Tables

### pf_competitions

Tournament/bracket competitions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `name` | VARCHAR(255) | Competition name |
| `description` | TEXT | Description |
| `competition_type` | VARCHAR(30) | 'single_elimination', 'double_elimination', 'round_robin' |
| `voting_method` | VARCHAR(30) | 'head_to_head', 'scored', 'ranked' |
| `rounds_count` | INT | Total rounds |
| `current_round` | INT | Current round |
| `status` | VARCHAR(20) | 'setup', 'active', 'completed' |
| `winner_performer_id` | BIGINT UNSIGNED | Winner FK |
| `runner_up_performer_id` | BIGINT UNSIGNED | Runner-up FK |
| `config` | LONGTEXT | JSON configuration |
| `scheduled_start` | DATETIME | Planned start |
| `started_at` | DATETIME | Actual start |
| `completed_at` | DATETIME | Completion time |
| `created_by` | BIGINT UNSIGNED | Admin user ID |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_status` (status)
- `idx_competition_type` (competition_type)

---

### pf_competition_matches

Individual matchups in competitions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `competition_id` | BIGINT UNSIGNED | FK to competitions |
| `round_number` | INT | Round number |
| `match_number` | INT | Match within round |
| `bracket_position` | VARCHAR(50) | Bracket location |
| `performer_1_id` | BIGINT UNSIGNED | First competitor |
| `performer_2_id` | BIGINT UNSIGNED | Second competitor |
| `performer_1_seed` | INT | Seed ranking |
| `performer_2_seed` | INT | Seed ranking |
| `winner_id` | BIGINT UNSIGNED | Match winner |
| `votes_performer_1` | INT | Vote count |
| `votes_performer_2` | INT | Vote count |
| `score_performer_1` | DECIMAL(10,2) | Judge score |
| `score_performer_2` | DECIMAL(10,2) | Judge score |
| `status` | VARCHAR(20) | 'pending', 'active', 'completed' |
| `scheduled_time` | DATETIME | Scheduled time |
| `voting_opens_at` | DATETIME | Voting opens |
| `voting_closes_at` | DATETIME | Voting closes |
| `started_at` | DATETIME | Match start |
| `completed_at` | DATETIME | Match end |
| `notes` | TEXT | Match notes |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_competition_id` (competition_id)
- `idx_round_number` (round_number)
- `idx_status` (status)
- `idx_performer_1_id` (performer_1_id)
- `idx_performer_2_id` (performer_2_id)
- `idx_scheduled_time` (scheduled_time)

---

### pf_match_votes

Ledger of accepted head-to-head votes (migration 1.7.1). Inserting the row is
the atomic "has not voted yet" check; the match's `votes_performer_N` counter
is then incremented in SQL.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `match_id` | BIGINT UNSIGNED | FK to competition_matches |
| `performer_id` | BIGINT UNSIGNED | Performer voted for |
| `voter_hash` | CHAR(64) | HMAC of the signed `pf_voter` cookie id or logged-in user |
| `client_hash` | CHAR(64) NULL | HMAC of client IP + User-Agent |
| `created_at` | DATETIME | Vote timestamp |

**Indexes:**
- `match_voter` UNIQUE (match_id, voter_hash)
- `match_client` UNIQUE (match_id, client_hash)
- `performer_id` (performer_id)

---

## Volunteer Tables

### pf_volunteers

Volunteer registrations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `user_id` | BIGINT UNSIGNED | WordPress user ID |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `name` | VARCHAR(255) | Full name |
| `email` | VARCHAR(255) | Email address |
| `phone` | VARCHAR(50) | Phone number |
| `emergency_contact` | VARCHAR(255) | Emergency contact name |
| `emergency_phone` | VARCHAR(50) | Emergency phone |
| `skills` | LONGTEXT | JSON skills list |
| `availability` | LONGTEXT | JSON availability |
| `shirt_size` | VARCHAR(10) | T-shirt size |
| `dietary_restrictions` | TEXT | Dietary needs |
| `status` | VARCHAR(20) | 'applied', 'approved', 'rejected' |
| `notes` | TEXT | Internal notes |
| `hours_completed` | DECIMAL(5,2) | Total hours worked |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_status` (status)
- `idx_user_id` (user_id)

---

### pf_volunteer_shifts

Available volunteer shifts.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `task_name` | VARCHAR(255) | Task name |
| `description` | TEXT | Task description |
| `location` | VARCHAR(255) | Shift location |
| `shift_date` | DATE | Shift date |
| `start_time` | TIME | Start time |
| `end_time` | TIME | End time |
| `slots_total` | INT | Volunteers needed |
| `slots_filled` | INT | Volunteers assigned |
| `status` | VARCHAR(20) | 'open', 'full', 'cancelled' |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_shift_date` (shift_date)
- `idx_status` (status)

---

### pf_volunteer_assignments

Links volunteers to shifts.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `shift_id` | BIGINT UNSIGNED | FK to shifts |
| `volunteer_id` | BIGINT UNSIGNED | FK to volunteers |
| `checked_in` | TINYINT(1) | Check-in status |
| `checked_in_at` | DATETIME | Check-in time |
| `checked_out_at` | DATETIME | Check-out time |
| `hours_worked` | DECIMAL(4,2) | Actual hours |
| `notes` | TEXT | Shift notes |

**Indexes:**
- `unique_assignment` (shift_id, volunteer_id)
- `idx_volunteer_id` (volunteer_id)

---

## Ticketing Tables

### pf_attendees

Festival attendee profiles.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `user_id` | BIGINT UNSIGNED | WordPress user ID |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `email` | VARCHAR(255) | Email address |
| `name` | VARCHAR(255) | Full name |
| `phone` | VARCHAR(50) | Phone number |
| `preferences` | LONGTEXT | JSON preferences |
| `created_at` | DATETIME | Creation timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_email` (email)
- `idx_user_id` (user_id)

---

### pf_tickets

Individual ticket records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `attendee_id` | BIGINT UNSIGNED | FK to attendees |
| `show_id` | BIGINT UNSIGNED | FK to shows |
| `eventbrite_order_id` | VARCHAR(100) | Eventbrite reference |
| `ticket_type` | VARCHAR(50) | Ticket tier |
| `quantity` | INT | Number of tickets |
| `total_paid` | DECIMAL(10,2) | Amount paid |
| `purchase_date` | DATETIME | Purchase timestamp |
| `status` | VARCHAR(20) | 'purchased', 'refunded', 'cancelled' |
| `qr_code` | VARCHAR(255) | QR code for check-in |
| `checked_in` | TINYINT(1) | Check-in status |
| `checked_in_at` | DATETIME | Check-in time |
| `ticket_code` | VARCHAR(20) | Code shown at the door (migration 1.0.1) |
| `payment_id` | VARCHAR(100) NULL | Stripe PaymentIntent id (migration 1.0.1) |
| `payment_amount` | DECIMAL(10,2) | Amount charged (migration 1.0.1) |
| `payment_status` | VARCHAR(20) | 'pending', 'completed', 'failed', 'refunded' (migration 1.0.1) |

**Indexes:**
- `idx_attendee_id` (attendee_id)
- `idx_show_id` (show_id)
- `idx_status` (status)
- `payment_id_unique` UNIQUE (payment_id) — one ticket per Stripe payment (migration 1.7.0).
  NULL payment ids (non-Stripe tickets) are not constrained. If duplicate rows
  already exist the index is deferred and reported; see SECURITY.md.

---

### pf_check_ins

Check-in audit log.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `ticket_id` | BIGINT UNSIGNED | FK to tickets |
| `show_id` | BIGINT UNSIGNED | FK to shows |
| `checked_in_by` | BIGINT UNSIGNED | Staff user ID |
| `check_in_method` | VARCHAR(20) | 'manual', 'qr', 'search' |
| `device_info` | TEXT | Device information |
| `location` | VARCHAR(255) | Check-in location |
| `notes` | TEXT | Additional notes |
| `checked_in_at` | DATETIME | Check-in time |

**Indexes:**
- `idx_ticket_id` (ticket_id)
- `idx_show_id` (show_id)
- `idx_checked_in_at` (checked_in_at)

---

## Vendor & Sponsor Tables

### pf_vendors

Food, merchandise, and service vendors.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `user_id` | BIGINT UNSIGNED | WordPress user ID |
| `business_name` | VARCHAR(255) | Business name |
| `contact_name` | VARCHAR(255) | Contact person |
| `email` | VARCHAR(255) | Email address |
| `phone` | VARCHAR(50) | Phone number |
| `vendor_type` | VARCHAR(50) | 'food', 'merchandise', 'service', etc. |
| `description` | TEXT | Business description |
| `products` | TEXT | Products/services offered |
| `booth_requirements` | TEXT | Booth needs |
| `electricity_needed` | TINYINT(1) | Power required |
| `booth_fee` | DECIMAL(10,2) | Booth fee |
| `fee_paid` | TINYINT(1) | Payment status |
| `booth_location` | VARCHAR(100) | Assigned location |
| `insurance_verified` | TINYINT(1) | Insurance confirmed |
| `license_verified` | TINYINT(1) | License confirmed |
| `status` | VARCHAR(20) | 'applied', 'approved', 'rejected' |
| `rating_internal` | DECIMAL(3,2) | Internal rating |
| `pros` | TEXT | Internal pros |
| `cons` | TEXT | Internal cons |
| `notes` | TEXT | Internal notes |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_vendor_type` (vendor_type)
- `idx_status` (status)

---

### pf_sponsors

Corporate and community sponsors.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `company_name` | VARCHAR(255) | Company name |
| `contact_name` | VARCHAR(255) | Contact person |
| `email` | VARCHAR(255) | Email address |
| `phone` | VARCHAR(50) | Phone number |
| `tier` | VARCHAR(50) | 'bronze', 'silver', 'gold', 'platinum' |
| `sponsorship_amount` | DECIMAL(10,2) | Cash sponsorship |
| `in_kind_value` | DECIMAL(10,2) | In-kind value |
| `in_kind_description` | TEXT | In-kind details |
| `benefits` | LONGTEXT | JSON benefits list |
| `logo_url` | VARCHAR(500) | Logo image URL |
| `website` | VARCHAR(500) | Company website |
| `social_links` | LONGTEXT | JSON social links |
| `contract_signed` | TINYINT(1) | Contract status |
| `payment_received` | TINYINT(1) | Payment status |
| `status` | VARCHAR(20) | 'prospect', 'confirmed', 'cancelled' |
| `notes` | TEXT | Internal notes |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_tier` (tier)
- `idx_status` (status)

---

## Financial Tables

### pf_transactions

All festival financial transactions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `transaction_type` | VARCHAR(20) | 'income', 'expense' |
| `category` | VARCHAR(100) | Transaction category |
| `amount` | DECIMAL(10,2) | Amount |
| `description` | TEXT | Description |
| `reference` | VARCHAR(255) | External reference |
| `reference_type` | VARCHAR(50) | 'sponsor', 'vendor', 'ticket', etc. |
| `reference_id` | BIGINT UNSIGNED | Related entity ID |
| `payment_method` | VARCHAR(50) | Payment method |
| `transaction_date` | DATE | Transaction date |
| `recorded_by` | BIGINT UNSIGNED | User who recorded |
| `created_at` | DATETIME | Creation timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_transaction_type` (transaction_type)
- `idx_category` (category)
- `idx_transaction_date` (transaction_date)
- `idx_festival_created` (festival_id, created_at)

---

### pf_coupons

Discount codes for tickets.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `vendor_id` | BIGINT UNSIGNED | Issuing vendor (optional) |
| `code` | VARCHAR(50) | Unique coupon code |
| `description` | TEXT | Description |
| `discount_type` | VARCHAR(20) | 'percentage', 'fixed' |
| `discount_value` | DECIMAL(10,2) | Discount amount |
| `valid_from` | DATE | Start date |
| `valid_until` | DATE | End date |
| `max_uses` | INT | Maximum redemptions |
| `times_used` | INT | Current redemptions |
| `status` | VARCHAR(20) | 'active', 'expired', 'disabled' |

**Indexes:**
- `unique_code` (code)
- `idx_festival_id` (festival_id)
- `idx_status` (status)

---

## Communication Tables

### pf_messages

Internal messaging system.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `conversation_id` | VARCHAR(100) | Conversation thread ID |
| `sender_id` | BIGINT UNSIGNED | Sender entity ID |
| `sender_type` | VARCHAR(20) | 'admin', 'performer', 'vendor', etc. |
| `recipient_id` | BIGINT UNSIGNED | Recipient entity ID |
| `recipient_type` | VARCHAR(20) | Recipient entity type |
| `subject` | VARCHAR(255) | Message subject |
| `content` | TEXT | Message body |
| `is_read` | TINYINT(1) | Read status |
| `is_broadcast` | TINYINT(1) | Broadcast message flag |
| `broadcast_group` | VARCHAR(50) | Target group for broadcasts |
| `created_at` | DATETIME | Creation timestamp |

**Indexes:**
- `idx_conversation_id` (conversation_id)
- `idx_sender` (sender_id, sender_type)
- `idx_recipient` (recipient_id, recipient_type)

---

### pf_email_logs

Email delivery tracking.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `recipient_email` | VARCHAR(255) | Email address |
| `recipient_name` | VARCHAR(255) | Recipient name |
| `subject` | VARCHAR(255) | Email subject |
| `template` | VARCHAR(100) | Template used |
| `status` | VARCHAR(20) | 'sent', 'failed', 'bounced' |
| `error_message` | TEXT | Error if failed |
| `sent_by` | BIGINT UNSIGNED | Sending user ID |
| `opened_at` | DATETIME | Open tracking |
| `clicked_at` | DATETIME | Click tracking |
| `created_at` | DATETIME | Send timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_recipient_email` (recipient_email)
- `idx_status` (status)
- `idx_created_at` (created_at)

---

## Administrative Tables

### pf_activity_log

Audit trail of all actions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `user_id` | BIGINT UNSIGNED | Acting user ID |
| `action` | VARCHAR(100) | Action performed |
| `entity_type` | VARCHAR(50) | Affected entity type |
| `entity_id` | BIGINT UNSIGNED | Affected entity ID |
| `details` | LONGTEXT | JSON action details |
| `ip_address` | VARCHAR(45) | IP address |
| `created_at` | DATETIME | Action timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_user_id` (user_id)
- `idx_action` (action)
- `idx_entity` (entity_type, entity_id)

---

### pf_issues

Issue/problem tracking.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_id` | BIGINT UNSIGNED | FK to festivals |
| `reporter_id` | BIGINT UNSIGNED | User who reported |
| `reporter_type` | VARCHAR(20) | Reporter entity type |
| `entity_type` | VARCHAR(50) | Related entity type |
| `entity_id` | BIGINT UNSIGNED | Related entity ID |
| `issue_type` | VARCHAR(20) | Issue category |
| `severity` | VARCHAR(20) | 'low', 'medium', 'high', 'critical' |
| `title` | VARCHAR(255) | Issue title |
| `description` | TEXT | Issue description |
| `status` | VARCHAR(20) | 'open', 'in_progress', 'resolved', 'closed' |
| `resolution` | TEXT | Resolution details |
| `resolved_by` | BIGINT UNSIGNED | Resolving user ID |
| `resolved_at` | DATETIME | Resolution timestamp |
| `created_at` | DATETIME | Creation timestamp |

**Indexes:**
- `idx_festival_id` (festival_id)
- `idx_entity` (entity_type, entity_id)
- `idx_status` (status)

---

### pf_job_queue

Background job processing.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `job_type` | VARCHAR(50) | Job type |
| `payload` | LONGTEXT | JSON job data |
| `status` | VARCHAR(20) | 'pending', 'processing', 'completed', 'failed' |
| `attempts` | TINYINT UNSIGNED | Attempt count |
| `last_error` | TEXT | Error message |
| `scheduled_at` | DATETIME | Scheduled run time |
| `started_at` | DATETIME | Processing start |
| `completed_at` | DATETIME | Completion time |
| `created_at` | DATETIME | Creation timestamp |

**Indexes:**
- `idx_status` (status)
- `idx_job_type` (job_type)
- `idx_scheduled_at` (scheduled_at)
- `idx_status_scheduled` (status, scheduled_at)

---

## Integration Tables

### pf_booker_links

Links Festival performers to Peanut Booker profiles.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `festival_performer_id` | BIGINT UNSIGNED | FK to performers (unique) |
| `booker_performer_id` | BIGINT UNSIGNED | Booker performer ID |
| `booker_user_id` | BIGINT UNSIGNED | Booker user ID |
| `booker_profile_id` | BIGINT UNSIGNED | Booker profile post ID |
| `sync_direction` | VARCHAR(20) | 'both', 'to_festival', 'to_booker' |
| `sync_status` | VARCHAR(20) | 'active', 'paused', 'error' |
| `booker_achievement_level` | VARCHAR(20) | Synced from Booker |
| `booker_rating` | DECIMAL(3,2) | Synced rating |
| `booker_completed_bookings` | INT | Synced booking count |
| `last_synced_at` | DATETIME | Last sync time |
| `created_at` | DATETIME | Creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

**Indexes:**
- `unique_festival_performer_id` (festival_performer_id)
- `idx_booker_performer_id` (booker_performer_id)
- `idx_booker_user_id` (booker_user_id)
- `idx_sync_status` (sync_status)

---

## Maintenance

### Cleanup Queries

```sql
-- Delete old votes (> 1 year, keeps finals)
DELETE FROM wp_pf_votes
WHERE voted_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Delete old activity logs (> 90 days)
DELETE FROM wp_pf_activity_log
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Delete old email logs (> 1 year)
DELETE FROM wp_pf_email_logs
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Clean completed jobs (> 30 days)
DELETE FROM wp_pf_job_queue
WHERE status = 'completed'
AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Clean failed jobs (> 7 days)
DELETE FROM wp_pf_job_queue
WHERE status = 'failed'
AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Index Optimization

Recommended periodic maintenance:

```sql
ANALYZE TABLE wp_pf_festivals;
ANALYZE TABLE wp_pf_shows;
ANALYZE TABLE wp_pf_performers;
ANALYZE TABLE wp_pf_votes;
ANALYZE TABLE wp_pf_tickets;
ANALYZE TABLE wp_pf_transactions;
```
