# Firebase Ownership and End State

This document defines the Firebase boundary for Peanut Festival. It is an ownership contract, not authorization to create a project, authenticate with Firebase, deploy rules, inspect live data, or change credentials.

## Architectural role

Peanut Festival uses an optional, installation-specific Firebase Realtime Database for public live state such as festival status, matches, leaderboards, votes, and performer updates. Firebase Cloud Messaging is also optional for push notifications.

This integration is not Firestore, is not the shared `peanut-suite` Firebase backend, and is not HULLABALOO's legacy Notebook migration source. A WordPress installation can operate with Firebase disabled.

Current repository truth:

- `includes/class-firebase.php` owns service-account-authenticated Realtime Database and Cloud Messaging calls.
- `includes/class-realtime-sync.php` maps WordPress festival state into Realtime Database paths.
- `firebase/database.rules.json` is the repository-owned rules source.
- `public/js/pf-firebase-client.js` is the browser client for live reads, voting, subscriptions, and messaging.
- The repository deliberately has no `.firebaserc` and names no production project. Project identity is per installation.
- Firebase project creation, rules deployment, credentials, backup, revision, health, and rollback are not automated or verified by repository CI.

## Ownership

| Concern | Accountable owner | Source of truth |
|---|---|---|
| Plugin integration and data-path schema | Peanut Graphic | PHP/JavaScript source and tests in this repository |
| Realtime Database rules source | Peanut Graphic | `firebase/database.rules.json` |
| Firebase project, billing, region, and enabled products | Operator for each WordPress installation | Approved project inventory outside source control |
| Service-account and VAPID credentials | Operator for each installation | Secret manager or protected WordPress configuration; never Git |
| Live Firebase data, retention, export, and restore | Operator for each installation | Approved project-specific runbook and evidence |
| WordPress package release | Peanut Graphic | Canonical signed plugin publisher |
| Rules/configuration promotion | Peanut Graphic plus the affected installation owner | Separate, project-scoped approval and recorded revision evidence |

The plugin repository owns the compatible contract. It does not silently become owner of every operator's Firebase account or production data.

## End-state contract

Firebase remains an optional per-install adjunct. WordPress is the authoritative transactional store; Firebase contains derived realtime state and messaging configuration. New first-party shared backend features belong in the separately owned Peanut Suite backend, not in an implicit shared Festival project.

A plugin release and a Firebase change are separate promotions:

1. A plugin release must not deploy Firebase rules or configuration.
2. A Firebase promotion must name one exact project, the rules digest/revision, the data owner, a pre-change export or other tested recovery point, validation checks, and a rollback action.
3. Rules must be promoted from `firebase/database.rules.json`; console-only edits are drift and must be reconciled back to reviewed source.
4. Credentials must fail closed and remain installation-specific. They must never be copied into a package, log, issue, test fixture, or repository.
5. Disabling or retiring an installation must remove its scheduled sync, revoke its credentials, preserve any required export, and record whether the Firebase project is retained or deleted. Deletion is destructive and always requires separate approval.

## Evidence required before production use

For each installation, record without customer data:

- Firebase project ID and owning account/team;
- Realtime Database region and active rules revision/digest;
- whether Cloud Messaging is enabled and who owns its VAPID credentials;
- data classification, retention, export cadence, restore owner, and last restore rehearsal;
- plugin version and exact rules compatibility;
- health and rollback evidence for a candidate change.

Until that inventory exists, production state remains `unverified`. The manual CLI examples in `docs/DEPLOYMENT.md` describe operator mechanics only; they are not a standing deployment authorization.

## Approval boundary

Source review, tests, and documentation are safe. Firebase login, project creation, rules/configuration deployment, data reads or writes, export/restore, credential work, billing/hosting changes, and project deletion require fresh project-specific approval.
