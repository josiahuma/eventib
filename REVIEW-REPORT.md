# Eventib pre-deployment review — 1 October 2026

Basis: your newly uploaded app(3), resources(3) and routes(2) ZIPs. Reviewed 96 PHP source files and 119 Blade views through source inspection, references and targeted flow tracing. These counts do not mean every path was runtime tested. Your accepted homepage view matches the latest supplied update and is preserved.

## Verified source defects corrected

| Area | Finding and correction |
|---|---|
| Route caching | Two duplicate named routes (organisers.edit and tickets.scan). Alternate URLs retain distinct names. |
| Admin permissions | Sponsor actions had only auth middleware. Every sponsor action now requires is_admin. |
| Broken navigation | MyTickets referenced nonexistent my.tickets.index; now uses my.tickets. Events index referenced a missing view; now redirects to Manage Events. Organiser index lacked a controller method; a private profile list is supplied. |
| Dead endpoints | Four digital-pass sample/finaliser routes and two optional ticket-list/PDF-export routes pointed to absent methods. Removed these unfinished routes; the current voice setup, ticket views/PDFs, scanner and check-in flows remain. Archived event-index/export templates had unsupported QR payloads and are no longer routed. Implement these features properly before exposing those endpoints. |
| Paid ticket editing | Legacy ticket_cost was the only paid check, but paid category events store it as zero. Paid amount or category items now lock companion/session editing on both edit flows. |
| Session edits | My Tickets now validates each chosen session belongs to the event rather than silently dropping foreign session IDs. |
| Card pricing | Legacy single-price paid events appeared as Free without ticket categories. Cards now show their actual price. |
| Duplicate warning | Paid single-price bookings incorrectly triggered free-booking warnings. Paid detection now uses active priced categories or the legacy price. |
| Checkout drafts | Reusing a pending registration could replace its items while an older Checkout remained payable. Each checkout now has its own registration draft. Older pending drafts remain for reconciliation; no records were deleted. |
| Payment cancellation | Cancel URL could overwrite paid status. Cancellation now only updates pending registrations. |
| Webhook verification | Missing webhook secret previously enabled unsigned input. It now returns 503; invalid signatures return 400. Set services.stripe.webhook_secret before accepting live payments. |
| Payment completion | Result page and webhook independently read unpaid state and could notify twice. Both now use a shared compare-and-set status claim; only the successful updater sends notifications. A successful result requires a registration matched to that Stripe session and event. |
| Webhook binding | Metadata alone could settle a different/reused registration. Now requires the saved Checkout session ID and verifies registration/event metadata when present. A webhook arriving before session ID persistence returns 503 for retry. |
| Schema dependency | Removed an optional registration stripe_payment_intent_id write; it is absent from the previously supplied registration migrations. No new column needed. Unlock payment intent storage is retained on its existing table. |
| Unlock purchase | Success URL now verifies payment purpose, event and user metadata. Webhook unlock handling locks the owning event and suppresses repeat notifications for an already unlocked purchase. |
| QR admission | Pending/paid registrations could generate and scan a free pass. Free pass generation/scanning now checks active free status; paid ticket scans check paid registration status. Unsupported legacy token method is guarded and token matching is exact and event-scoped. Free category pass party counts use ticket quantity. |
| Descriptions | Raw user HTML rendered publicly. Added a DOM whitelist sanitizer that retains basic text formatting and safe links while removing executable HTML and event attributes. Advanced styling, embeds and images are intentionally stripped. |
| Remote imports | URL fetches accepted private/internal destinations. Added public IPv4 validation, DNS pinning, proxy disabling, redirect rejection and a 12 MB download limit. Imported banners are decoded and checked as JPG/PNG/WebP before storage. Requires PHP cURL; use the final destination URL for redirected links. IPv6-only imports are currently unsupported. |
| Payout requests | Transactions did not lock an event before recomputing funds. Event row now locks competing payout requests. |
| Passed fees | Payout availability deducted 5.9% even for fees passed separately to attendees. PASS now retains full ticket revenue, consistent with the registrants dashboard; ABSORB retains the existing 5.9% calculation. Historical transactions should still be reconciled. |
| Archive | Disabled and recurring future events could appear in Past Events. Now excludes disabled events and any event with upcoming sessions. |
| Dates and tags | Discovery sorts and results use upcoming sessions rather than earliest historical sessions. New/edited tags now assign arrays to the existing array cast instead of double-encoding JSON. Existing stored tags are not migrated. |
| Free confirmation mail | Mail exceptions could return a 500 after a free registration was saved. They are reported without failing the confirmation response. This does not guarantee delivery; review mail logs. |

## Checks actually executed

- Latest uploaded registration JavaScript executed through the booking harness; paid/free/mixed pricing, fee rounding, quantity bounds, session gating, child-age selection preservation and shrinking passed.
- Public booking JavaScript syntax check passed.
- All directly registered controller methods resolve after the patch.
- Literal controller/mailable view references resolve after the patch.
- ZIP integrity and SHA-256 manifest checked.
- Accepted homepage view was compared against the latest supplied update and matched exactly.

## Added tests, not executed here

EventReleaseReviewTest covers admin access, route-name uniqueness, missing webhook secret, signed webhook repeat handling, paid cancellation protection, paid companion edits, card pricing/duplicate warnings, HTML sanitization, archived event visibility, private URL rejection, pending free-pass denial, and passed-fee payout availability. It uses RefreshDatabase: run only against a separate disposable test database.

## Deployment conditions and remaining gaps

This is a source review and fix patch, not production sign-off. No PHP executable or runnable Laravel/composer/bootstrap configuration is available here. PHP lint, Blade compilation, actual route caching, Laravel tests, browser rendering, DB transactions under concurrency, real mail and Stripe sandbox payments were not executed.

Your latest uploads did not include database, public, composer files, bootstrap or config. Matching previously generated public assets are included. Confirm your actual schema includes events.faqs, event_saves, organiser subscriptions/alert outbox and event_registrations.child_ages from the previous migrations. No new migration is introduced here.

**Capacity remains unenforced.** Event/category capacity fields are stored but neither free nor paid booking flows implement capacity reservation/enforcement. Current creation help text claiming that the limit is enforced is inaccurate. Do not rely on those values for a venue limit or sold-out inventory until an atomic reservation workflow is implemented. Free registration duplicate checks are also not concurrency-safe: simultaneous requests can both pass the existing duplicate check. These need a dedicated transactional booking change and real DB tests.

**Delivery remains best effort.** A payment claim prevents competing result/webhook notifications; it does not guarantee delivery after a mail failure or a process crash. There is no durable registration-mail retry outbox. Unlock completion through the success page before the webhook can suppress webhook emails, because the unlock is already persisted. Review mail/queue logs and handle missing receipts manually until notification outbox work is completed.

Verify webhook CSRF exclusion in bootstrap/app.php (not uploaded), HTTPS APP_URL for signed links, the real Stripe webhook secret, queue workers for existing queued mails, and the scheduler for organiser alerts. Verify PHP upload_max_filesize 12M, post_max_size 32M and corresponding web-server limits. No credentials or VPS configuration were accessed or changed.

Run free booking with two children, mixed ticket category selection, a paid Stripe test checkout, repeated webhook delivery, paid cancellation protection, QR admission, event creation with a banner over 4 MB, organiser alert opt-in and signed unsubscribe on your full local project before pushing to GitHub/VPS.
