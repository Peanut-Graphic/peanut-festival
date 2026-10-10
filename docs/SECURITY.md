# Security Documentation

## Overview

Peanut Festival implements security measures focused on voting integrity, payment processing, and protecting performer and festival data.

## Authentication & Authorization

### REST API Endpoints

| Endpoint Type | Authentication | Authorization |
|--------------|----------------|---------------|
| Public voting | None (token-based fraud prevention) | Rate limited |
| Festival data | None (public) | Read-only |
| Admin endpoints | WordPress auth | `manage_options` capability |
| Performer updates | WordPress auth | Festival admin or performer |

### Admin Permission Callback

```php
public static function permission_admin(): bool|WP_Error {
    if (!current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', 'Unauthorized', ['status' => 403]);
    }
    return true;
}
```

## Voting Security

### Multi-Layer Fraud Prevention

| Layer | Purpose | Implementation |
|-------|---------|----------------|
| IP Hash | Prevent repeat votes | MD5 hash stored, not raw IP |
| Token | Anonymous user tracking | Random UUID per voter session |
| Fingerprint | Device fingerprinting | Hash of browser characteristics |
| Rate Limit | Prevent rapid voting | Max 10 votes/minute |

### Duplicate Vote Detection

```php
// Check for existing vote
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM {$table}
     WHERE show_slug = %s
     AND group_name = %s
     AND (ip_hash = %s OR token = %s OR fingerprint_hash = %s)",
    $show_slug, $group, $ip_hash, $token, $fingerprint_hash
));
```

### Show-Vote Ballot Rules

`POST /vote/submit` accepts one ballot per voter per show group:

- `performer_ids` must be distinct, positive ids that all belong to the show's
  active group, at most `Peanut_Festival_Voting::MAX_BALLOT_RANKS` (3, one per
  weighted rank). Repeats collapse to their first rank; anything else rejects
  the whole ballot with `400 invalid_ballot`.
- Each row carries `ballot_key` = HMAC(show, group, voter IP hash), and
  `UNIQUE(ballot_key, vote_rank)` makes the rank-1 insert the atomic "first
  ballot wins" point, so two submissions racing past the `has_voted()` check
  cannot both be recorded. Administrator test votes keep `ballot_key` NULL.

### Head-to-Head Match Votes

`POST /matches/{id}/vote`:

| Control | Implementation |
|---------|----------------|
| Same-site only | `Peanut_Festival_Request_Guard::require_same_origin()` — Origin (or Referer) host must be this site; filter `peanut_festival_allowed_request_hosts` |
| Rate limit | `match_vote`: 10 requests/minute per client IP |
| Voter identity | `Peanut_Festival_Voter_Identity`: logged-in user, else a random id in an HMAC-signed, HttpOnly, SameSite=Lax first-party `pf_voter` cookie; any client-supplied `voter_id` is ignored |
| Network identity | Keyed hash of client IP + User-Agent |
| One vote per match | `pf_match_votes` UNIQUE (match_id, voter_hash) and UNIQUE (match_id, client_hash) |
| Atomic count | `UPDATE ... SET votes_performer_N = votes_performer_N + 1 WHERE id = ? AND status = 'voting'` |

Residual risk: a determined attacker who rotates IP addresses *and* discards
cookies can still cast more than one vote; the per-IP rate limit bounds the
rate. Live-event voting behind one venue NAT relies on the cookie + User-Agent
to tell audience members apart, so a stricter per-IP rule would block
legitimate voters.

### Vote Time Windows

Votes are only accepted when:
1. Voting is enabled for the show
2. Current time is within start/end window
3. Show is not archived

### CSRF Protection

All voting endpoints verify nonce:

```php
if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
    return new WP_Error('invalid_nonce', 'Invalid security token', ['status' => 403]);
}
```

## Payment Security

### Stripe Integration

Payments are processed through Stripe with these protections:

| Protection | Implementation |
|------------|----------------|
| API Keys | Environment variables preferred |
| Webhook Signature | HMAC verification |
| PCI Compliance | No card data touches server |

### API Key Storage

```php
// Priority: Environment > Constant > Database
private static function get_stripe_key(): string {
    // Check environment variable first
    $env_value = getenv('STRIPE_SECRET_KEY');
    if ($env_value) return $env_value;

    // Check constant
    if (defined('STRIPE_SECRET_KEY')) return STRIPE_SECRET_KEY;

    // Fall back to database (with admin warning)
    return get_option('pf_stripe_secret_key', '');
}
```

### One Ticket per Payment

`POST /payments/confirm` is public, so it must be safe to replay:

1. The PaymentIntent id must match `^pi_[A-Za-z0-9]+$` before it is used in a
   Stripe API path, and Stripe must report that same id as `succeeded`.
2. If a ticket already exists for the payment, it is returned unchanged.
3. Otherwise a per-payment MySQL advisory lock (`GET_LOCK`) serialises the
   re-check and the insert across the confirm endpoint, the
   `payment_intent.succeeded` webhook and concurrent PHP workers. A confirm
   that cannot get the lock returns `409 payment_processing`; retrying returns
   the same ticket.
4. `UNIQUE(tickets.payment_id)` (migration 1.7.0) refuses any second insert.

**Existing duplicates.** If the UNIQUE index cannot be added because some
payments already have more than one ticket, migration 1.7.0 still succeeds,
deletes nothing, stores the affected payment ids and ticket ids in the
`peanut_festival_ticket_payment_duplicates` option, logs the counts, and shows
administrators an admin notice. Resolve each payment (check Stripe, void or
refund the extra tickets, then delete the extra rows); the index is re-checked
hourly from `admin_init` and added automatically once no duplicates remain.

### Webhook Verification

```php
// Verify Stripe webhook signature
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = \Stripe\Webhook::constructEvent(
    $payload,
    $sig_header,
    $webhook_secret
);
```

## SQL Injection Prevention

### Parameterized Queries

All database operations use prepared statements:

```php
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table}
     WHERE festival_id = %d
     AND category = %s
     ORDER BY transaction_date DESC",
    $festival_id,
    $category
));
```

### Dynamic ORDER BY

```php
$allowed_columns = ['created_at', 'name', 'votes'];
$orderby = in_array($request_orderby, $allowed_columns, true)
    ? $request_orderby
    : 'created_at';
```

### LIKE Clause Escaping

```php
$search = '%' . $wpdb->esc_like($search_term) . '%';
$query = $wpdb->prepare("SELECT * FROM {$table} WHERE name LIKE %s", $search);
```

## Input Validation

### Festival Data

| Field | Validation |
|-------|------------|
| `name` | `sanitize_text_field()`, max 255 chars |
| `slug` | `sanitize_title()`, unique check |
| `dates` | Valid date format, end >= start |
| `venue_address` | `sanitize_textarea_field()` |

### Performer Data

```php
// Sanitize performer submission
$data = [
    'name' => sanitize_text_field($request['name']),
    'email' => sanitize_email($request['email']),
    'bio' => wp_kses_post($request['bio']),
    'social_links' => array_map('esc_url_raw', $request['social_links'] ?? []),
];
```

### Voting Data

```php
// Validate performer IDs in ballot
$valid_performers = get_show_performers($show_slug);
foreach ($ballot as $performer_id) {
    if (!in_array($performer_id, $valid_performers, true)) {
        return new WP_Error('invalid_performer', 'Invalid performer ID');
    }
}
```

## Rate Limiting

### Configuration

Limits live in `Peanut_Festival_Rate_Limiter::$limits` (requests per window,
per client IP):

```php
'vote'        => ['limit' => 10, 'window' => 60],   // show-vote submissions
'match_vote'  => ['limit' => 10, 'window' => 60],   // head-to-head votes
'application' => ['limit' => 5,  'window' => 300],  // performer/vendor/volunteer forms
'payment'     => ['limit' => 10, 'window' => 60],   // create-intent / confirm
'general'     => ['limit' => 60, 'window' => 60],   // everything else
```

### Implementation

`enforce()` returns a ready `429` `WP_REST_Response` (with `Retry-After` and
`X-RateLimit-*` headers) when the bucket is exhausted, or `null`:

```php
$rate_limit = Peanut_Festival_Rate_Limiter::enforce('vote');
if ($rate_limit !== null) {
    return $rate_limit;
}
```

### Client IP and Trusted Proxies

`Peanut_Festival_Rate_Limiter::get_client_ip()` uses `REMOTE_ADDR`.
`X-Forwarded-For` and `X-Real-IP` are client-controlled and are only believed
when `REMOTE_ADDR` is a configured trusted proxy; `X-Forwarded-For` is then
walked from the right and the first hop that is not itself a trusted proxy is
the client. Configure proxies (IPs or CIDR ranges, IPv4/IPv6) with the
administrator-only `trusted_proxies` setting or:

```php
add_filter('peanut_festival_trusted_proxies', function () {
    return ['10.0.0.0/8', '2001:db8::/32']; // your load balancer / CDN ranges
});
```

Default: no trusted proxies. The voting IP hashes and the logger use the same
resolver. Behind a CDN or load balancer, configure it, or every visitor will
share the proxy's rate-limit bucket.

## Settings Secrets

`GET /admin/settings` never returns integration secrets in plaintext, for any
caller: `stripe_test_secret_key`, `stripe_live_secret_key`,
`stripe_webhook_secret`, `firebase_service_account`, `eventbrite_token`,
`eventbrite_client_secret`, `eventbrite_webhook_secret`, `mailchimp_api_key`,
`booker_api_key`, `ml_api_key`. Each is masked (`••••` + last 4 characters, or
`••••` for short or structured values) and `_secrets.{key}` reports
`has_value` and `from_env`.

`PUT /admin/settings` accepts only an allowlist of keys, each sanitised for its
type; unknown keys are ignored. A secret sent back as its mask (or unchanged) is
left alone, so the settings form round-trips. Changing a secret, or any of
`stripe_test_mode`, `stripe_*_publishable_key`, `booker_api_url`,
`trusted_proxies`, requires `manage_options`: the Festival Producer role
(`manage_pf_festival`) receives `403 forbidden_setting`. Uploading a Firebase
service account via `PUT /admin/firebase/settings` is also administrator-only.

## Firebase Security

### Admin SDK Credentials

Firebase credentials are stored securely:

```php
// Credentials stored as WordPress option (encrypted)
// Never exposed in JavaScript or public endpoints
$credentials = json_decode(
    Peanut_Festival_Encryption::decrypt(
        get_option('pf_firebase_credentials')
    ),
    true
);
```

### Database Rules

Firebase Realtime Database rules restrict access:

```json
{
  "rules": {
    "festivals": {
      "$festivalId": {
        ".read": true,
        ".write": "auth != null && root.child('admins').child(auth.uid).exists()"
      }
    }
  }
}
```

## Data Privacy

### Voter Privacy

- IP addresses stored as one-way hash
- No personally identifiable information collected
- Voting tokens are random and unlinkable

### Performer Data

| Data Type | Visibility | Storage |
|-----------|------------|---------|
| Public bio | Public | Plain text |
| Email | Festival admins only | Encrypted |
| Phone | Festival admins only | Encrypted |
| Payment info | Owner only | Stripe (not on server) |

## Security Logging

```php
Peanut_Festival_Logger::log('security', 'vote_fraud_detected', [
    'show_slug' => $show_slug,
    'ip_hash' => $ip_hash,
    'fingerprint' => $fingerprint_hash,
    'reason' => 'duplicate_fingerprint',
]);
```

## Security Headers

```php
// Set on API responses
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

## Reporting Security Issues

Report vulnerabilities to: security@peanutgraphic.com

**Responsible Disclosure**: Please allow 90 days for remediation before public disclosure.

## Security Checklist

### For Festival Administrators

- [ ] Use strong passwords
- [ ] Enable two-factor authentication
- [ ] Regularly review vote patterns for anomalies
- [ ] Keep WordPress and plugins updated
- [ ] Use HTTPS for all festival pages

### For Developers

- [ ] All user input validated and sanitized
- [ ] SQL queries use prepared statements
- [ ] Vote fraud detection enabled
- [ ] Rate limiting configured
- [ ] Stripe API keys in environment variables
- [ ] Firebase credentials encrypted
