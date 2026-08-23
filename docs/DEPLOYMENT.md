# Peanut Festival - Deployment Guide

This guide covers deploying Peanut Festival to production environments.

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Firebase Setup](#firebase-setup)
5. [Stripe Integration](#stripe-integration)
6. [Production Deployment](#production-deployment)
7. [Performance Optimization](#performance-optimization)
8. [Monitoring](#monitoring)
9. [Troubleshooting](#troubleshooting)

---

## Requirements

### Server Requirements

- **PHP**: 8.0 or higher (8.2+ recommended)
- **WordPress**: 6.0 or higher
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Memory**: 256MB PHP memory limit minimum
- **SSL**: HTTPS required for Firebase and Stripe

### PHP Extensions

- `json` (usually enabled by default)
- `mbstring`
- `openssl`
- `curl`
- `dom` (for PDF generation)

### Optional Services

- Firebase (real-time features, push notifications)
- Stripe (payment processing)
- Eventbrite (event sync)
- Mailchimp (email marketing)

---

## Installation

### Standard WordPress Installation

1. Download the plugin ZIP file
2. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**
3. Upload and activate

### Manual Installation

```bash
# Navigate to plugins directory
cd /path/to/wordpress/wp-content/plugins

# Extract plugin
unzip peanut-festival.zip

# Set permissions
chmod -R 755 peanut-festival
chown -R www-data:www-data peanut-festival
```

### Composer (Development)

```bash
cd peanut-festival
composer install --no-dev --optimize-autoloader
```

### Frontend Build (Development)

```bash
cd peanut-festival/frontend
npm ci
npm run build
```

---

## Configuration

### Environment Variables (Recommended)

For production, use environment variables instead of storing credentials in the database.

Create a `.env` file in your WordPress root or configure your server:

```bash
# Apache (in .htaccess or vhost config)
SetEnv PEANUT_FESTIVAL_FIREBASE_API_KEY "your-api-key"

# Nginx (in server block)
fastcgi_param PEANUT_FESTIVAL_FIREBASE_API_KEY "your-api-key";

# Docker
environment:
  - PEANUT_FESTIVAL_FIREBASE_API_KEY=your-api-key

# wp-config.php (alternative)
putenv('PEANUT_FESTIVAL_FIREBASE_API_KEY=your-api-key');
```

See `.env.example` for all available environment variables.

### Database Settings

For non-sensitive settings, use the WordPress admin:

1. Go to **Peanut Festival > Settings**
2. Configure general settings, voting rules, display options

---

## Firebase Setup

Read [Firebase Ownership and End State](FIREBASE-OWNERSHIP.md) before configuring an installation. The commands below describe operator mechanics; they do not authorize Firebase login, project creation, rules deployment, credential changes, or live-data access.

### 1. Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or select existing
3. Enable **Realtime Database** (not Firestore)
4. Set database location close to your server

### 2. Configure Security Rules

Deploy the security rules from `firebase/database.rules.json`:

```bash
# Install Firebase CLI
npm install -g firebase-tools

# Login
firebase login

# Initialize (select only Realtime Database)
firebase init database

# Deploy rules
firebase deploy --only database
```

Or paste the rules manually in Firebase Console > Realtime Database > Rules.

### 3. Create Service Account

1. Firebase Console > Project Settings > Service Accounts
2. Click **Generate new private key**
3. Save the JSON file securely
4. Either:
   - Paste JSON in admin settings, OR
   - Base64 encode and set as environment variable:
     ```bash
     cat service-account.json | base64 | tr -d '\n'
     ```

### 4. Enable Cloud Messaging (Optional)

1. Firebase Console > Project Settings > Cloud Messaging
2. Generate VAPID key pair
3. Add to plugin settings

---

## Stripe Integration

### 1. Get API Keys

1. Log into [Stripe Dashboard](https://dashboard.stripe.com/)
2. Get API keys from Developers > API keys
3. Add to environment:
   ```
   PEANUT_FESTIVAL_STRIPE_SECRET_KEY=sk_live_...
   PEANUT_FESTIVAL_STRIPE_PUBLISHABLE_KEY=pk_live_...
   ```

### 2. Configure Webhook

1. Stripe Dashboard > Developers > Webhooks
2. Add endpoint: `https://yoursite.com/wp-json/peanut-festival/v1/stripe/webhook`
3. Select events:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `checkout.session.completed`
4. Copy webhook secret to environment:
   ```
   PEANUT_FESTIVAL_STRIPE_WEBHOOK_SECRET=whsec_...
   ```

---

## Production Deployment

### Pre-Deployment Checklist

- [ ] All environment variables configured
- [ ] Database migrations run
- [ ] Frontend assets built
- [ ] SSL certificate installed
- [ ] Firebase security rules deployed
- [ ] Stripe webhook configured
- [ ] Cron jobs verified

### Database Migrations

Migrations run automatically on plugin activation. To verify:

```php
// In wp-config.php or via WP-CLI
define('WP_DEBUG', true);

// Check migration status
// Look for: "Peanut Festival Migration" in debug.log
```

### Cron Jobs

The plugin uses WordPress cron. For reliable scheduling:

```bash
# Disable WordPress internal cron
# In wp-config.php:
define('DISABLE_WP_CRON', true);

# Add server cron (every minute)
* * * * * cd /path/to/wordpress && wp cron event run --due-now
```

### Cache Configuration

If using object caching (Redis/Memcached):

```php
// The plugin is cache-aware. No additional configuration needed.
// Transients are used for rate limiting and vote deduplication.
```

### CDN Setup

For assets served from a CDN:

1. Configure WordPress CDN plugin (e.g., W3 Total Cache)
2. Ensure `/wp-content/plugins/peanut-festival/assets/` is cached
3. Set appropriate cache headers for static files

---

## Performance Optimization

### Database Indexes

The plugin creates indexes automatically. Verify with:

```sql
SHOW INDEX FROM wp_pf_votes;
SHOW INDEX FROM wp_pf_shows;
SHOW INDEX FROM wp_pf_performers;
```

### PHP OpCache

Ensure OpCache is enabled:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
```

### Query Monitor

Install Query Monitor plugin to identify slow queries during development.

### Rate Limiting

Default rate limits (configurable in settings):

- Votes: 10 per minute per IP
- API requests: 60 per minute per IP

---

## Monitoring

### Health Checks

The plugin exposes a health endpoint:

```
GET /wp-json/peanut-festival/v1/health
```

Response:
```json
{
  "status": "ok",
  "database": "connected",
  "firebase": "enabled",
  "version": "1.3.0"
}
```

### Logs

Plugin logs are written to WordPress debug log:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs location: `/wp-content/debug.log`

### Metrics to Monitor

- Voting throughput (votes/minute)
- API response times
- Firebase sync latency
- Database query times
- Error rates

---

## Troubleshooting

### Common Issues

#### "Firebase sync failed"

1. Check service account credentials
2. Verify database URL is correct
3. Confirm security rules allow writes
4. Check server can reach `*.googleapis.com`

#### "Stripe webhook not working"

1. Verify webhook secret matches
2. Check endpoint is accessible (no auth blocking)
3. Confirm SSL certificate is valid
4. Check Stripe webhook logs for errors

#### "Admin page blank/500 error"

1. Enable WP_DEBUG to see error
2. Check PHP memory limit
3. Verify all plugin files are present
4. Check for plugin conflicts

#### "Voting not recording"

1. Check rate limiting isn't blocking
2. Verify voting window is open
3. Check browser console for JavaScript errors
4. Confirm database table exists

### Debug Mode

Enable debug output:

```php
// In wp-config.php
define('PEANUT_FESTIVAL_DEBUG', true);
```

### Support

For issues, check:

1. WordPress debug log
2. Browser console
3. Network tab for failed requests
4. Firebase console (if using Firebase)
5. Stripe dashboard (for payment issues)

---

## Security Best Practices

1. **Always use HTTPS** in production
2. **Use environment variables** for all credentials
3. **Regular updates**: Keep WordPress, PHP, and the plugin updated
4. **Limit admin access**: Use strong passwords and 2FA
5. **Backup database** before major events
6. **Monitor access logs** for unusual activity
7. **Review Firebase rules** after any changes

---

## Updating

### Standard Update

1. Backup database
2. Deactivate plugin
3. Replace plugin files
4. Reactivate plugin
5. Migrations run automatically

### Via WP-CLI

```bash
wp plugin deactivate peanut-festival
wp plugin update peanut-festival --version=1.3.0
wp plugin activate peanut-festival
```

---

## Rollback

If issues occur after update:

1. Deactivate current version
2. Replace with previous version files
3. Activate previous version
4. Check for data migration issues

Note: Some migrations may not be reversible. Always backup before updating.
