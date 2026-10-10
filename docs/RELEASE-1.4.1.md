# Peanut Festival 1.4.1 release and reconciliation

This patch includes PR #76 plus complete duplicate-ticket reporting and safer
operator guidance. Plugin version 1.4.1 and database schema 1.7.2 are separate
version numbers. A schema version alone does not prove that the deferred payment
uniqueness index exists.

## Duplicate payments: preserve evidence and legitimate admissions

Before upgrading, take a recoverable database backup and export affected tickets,
attendees, transactions, and check-in rows to access-controlled storage. Use the
actual WordPress table prefix. Do not paste customer or Stripe data into public
issues. Inventory groups without GROUP_CONCAT, which can truncate IDs:

```sql
SELECT payment_id, COUNT(*) AS ticket_count
FROM wp_pf_tickets
WHERE payment_id IS NOT NULL AND payment_id <> ''
GROUP BY payment_id HAVING COUNT(*) > 1;
```

For each returned payment ID, retrieve every ticket row using a parameterized
`SELECT ... WHERE payment_id = ? ORDER BY id` query. The migration report is
limited to 100 payment groups and the notice displays 20; the database inventory
is the source for a complete reconciliation.

Reconcile each group against Stripe's PaymentIntent, amount, currency, quantity,
refunds, and purchaser; compare ticket delivery, admission/check-in history, and
revenue transactions. One ticket row can represent multiple admissions via its
quantity. Do not assume that the earliest ticket is correct or that every extra
row is fraudulent. Replayed confirmations may also have duplicated revenue
transactions, which must be reconciled separately without inventing extra charges.

Choose a canonical payment-linked ticket only after an operator approves the
entitlement decision. Preserve every ticket ID, ticket code, quantity and check-in
reference. Record the original payment ID, canonical ticket ID, operator, date,
reason, Stripe evidence and resolution for every affected row in a durable,
access-controlled reconciliation record BEFORE changing payment links. For
verified replay extras, the operator can detach the redundant `payment_id` by
setting it to NULL while retaining the row and its original link in that record.
This is a manual, reviewed operation, not an automatic migration. Detaching the
link does not void a ticket; separately verify the existing admission controls
and the customer's legitimate quantity. If entitlement cannot be represented
safely, leave the group unchanged and the index deferred.

Do not delete ticket or check-in rows to satisfy the index. Do not refund a shared
PaymentIntent just because tickets are duplicated: that refunds the actual
purchase. Refund only an independently established financial obligation, with
explicit operator authorization. No reconciliation or refund is performed by
this release.

After reviewed reconciliation, the hourly `admin_init` check adds
`payment_id_unique`. Alternatively an administrator may call
`Peanut_Festival_Migrations::ensure_ticket_payment_unique_index()` deliberately
and inspect its returned status. Verify the index with SHOW INDEX, its
`Non_unique = 0`, and a repeat inventory returning no duplicate payment links.
Existing duplicate groups do not block migrations 1.7.1 and 1.7.2; the payment
advisory lock and existing-ticket lookup protect new fulfillment in the interim.

## Proxy and voting compatibility

Default `trusted_proxies` is empty. Leave it empty for direct origin traffic.
Behind a proxy, establish the actual TCP peer and which component overwrites or
appends forwarded headers; configure only those proxy IPs/CIDRs through the
administrator setting or `peanut_festival_trusted_proxies` filter. Do not trust
all addresses or infer safe ranges from an HTTP Server header. Test IPv4/IPv6,
multiple clients, forged headers, and venue Wi-Fi before enabling live voting.
An unconfigured proxy collapses client IP identity and rate-limit buckets.
Show voting still uses IP identity, so shared venue networks need an operational
acceptance test. Head-to-head cookie-plus-IP/UA deduplication is not proof of one
human vote; rotating both identities remains a residual risk.

Match voting requires same-site Origin/Referer. Review any separate voting host
and deliberately allow only intended hosts via
`peanut_festival_allowed_request_hosts`; do not use a wildcard. Test the actual
browser flow, cookies, closed ballots, retries, concurrency and live results.
The pre-existing realtime event mismatch (`match_vote_submitted` versus
`match_vote_recorded`) is not fixed here and must be considered for Firebase
consumers. Existing ballots have NULL ballot keys; the new unique index does not
retroactively deduplicate historical results.

## Signed publication and installation

Use the central `Peanut-meta/scripts/publish-plugin.sh peanut-festival 1.4.1`
for build/sign/verify, then its `--ship` mode only after release acceptance.
Its curated package must include vendor/, the locked formflow-core runtime,
admin/, assets/, includes/, public/, and both matching 1.4.1 declarations.
The archive is `peanut-festival-1.4.1.zip`, rooted at `peanut-festival/`; publish
its `.zip.manifest.json` sidecar alongside it. The manifest carries SHA-256,
detached Ed25519 signature, slug, version, asset, algorithm and key fingerprint.
Verify exact archive bytes against the pinned public key
`NtHnWTBLVzCBKMAq9CO8LHDSD9ZfpGV0UloQdgToIwM=`. Never distribute a signing secret.

The official publisher clones main, so a candidate branch must first be reviewed
and merged; a dry-run package is not a published release. Production vendor
install uses `composer install --no-dev` from the committed lock. Signature-gate
availability must be checked: a missing vendor verifier only raises a notice and
leaves verification unavailable. Ordinary tests of the wiring do not prove a
production update was verified.

The publisher's ship mode also advertises the version through the license-server
option and force-installs it on peanutgraphic.com. These are distinct external
mutations, not just uploading a GitHub asset. Establish a rollback package and
recoverable database backup before proceeding. Package rollback does not undo
schema migrations. Verify installation independently: active plugin 1.4.1,
schema 1.7.2, all ballot/ledger indexes, payment index or explicit deferred report,
no PHP errors, correct update API URL, manifest verification, and safe staging
payment/voting browser tests. Never use real charges or votes as unapproved tests.

If producer accounts existed while secrets were exposed, investigate access and
rotate affected integrations through an authorized operator; absence of current
producer accounts does not establish that no historical exposure occurred.

## Audit evidence (October 10, 2026)

Merged main baseline: f3ed710b55840eb9d242c55653f3966224a57703. GitHub CI jobs
including MySQL 8.4.11 and MariaDB 10.6.28 completed successfully. Local baseline
verification passed 416 PHP mock tests, four property tests, and 43 real WordPress
contracts on MariaDB 10.6.28. Candidate verification passed 416 PHP mock tests (947 assertions) and 44 real
WordPress contracts (185 assertions) on each of MySQL 8.4.11 and MariaDB 10.6.28.
The added regression proves complete duplicate reporting under a deliberately
small group_concat_max_len; reconciliation coverage preserves both ticket rows.
The release-readiness checker passes. A local production-dependency candidate ZIP
and Ed25519 sidecar were built using the central publisher package list and
verified against the pinned key; this is not official publication.

Read-only central-canary inspection found active plugin 1.4.0, schema 1.6.0,
WordPress 7.1.3, PHP 8.5.11, MariaDB 10.6.27, no duplicate payment groups, no
current producer accounts, and a loaded signing verifier. No trusted-proxy
setting or filter was configured. HTTP response reports Apache; this does not
establish the network topology. PHP runtime dependency allows >=8.0 and <8.6.
Database contracts cover the MariaDB 10.6 family; local PHP execution was 8.5.5,
so production 8.5.11 is not an exact local-runtime replication.

Residual fulfillment limits remain: ticket insertion, revenue recording and email
are separate operations, so a crash after insertion can leave missing side effects
that an existing-ticket retry does not repair. The succeeded webhook acknowledges
HTTP 200 even when fulfillment is busy/failed, so Stripe retry behavior is not a
recovery guarantee. Refund callbacks are not event-deduplicated. Match ledger
insertion and count increment likewise are separate writes. These are follow-up
reliability concerns, not claims covered by the replay/ballot contracts. Do not
describe this patch as proof of exactly-once financial processing or full
production voting integrity.

No festival venue installation, proxy correctness, historical secret exposure,
live payment processing, or live audience behavior is established by these
checks. Publication, canary upgrade and venue deployment require separate
verification; no production record was changed during this audit.
