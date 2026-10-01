# Apply the pre-deployment fixes

This ZIP is based on your latest uploaded source, not earlier originals. Merge complete replacement files into matching project paths. It preserves the accepted homepage. Also copy the included public assets; they match the latest homepage/booking patches and were not present in the uploaded ZIPs.

No new migration is required by this fix patch. The previous feature migrations must already be present and applied. Read REVIEW-REPORT.md for remaining capacity, concurrency and email-delivery gaps.

On your Windows LOCAL project, after copying files:
```powershell
powershell -ExecutionPolicy Bypass -File .\tools\eventib-preflight.ps1
```
This checks PHP syntax, Composer platform requirements, route/view caching, frontend booking checks and builds assets. It does not run destructive database tests or send email.

Then configure a separate test database in phpunit.xml/.env.testing, never your normal or production database, and run:
```powershell
php artisan test --filter=EventReleaseReviewTest
```
Also run the previous EventGrowthTest and EventDiscoveryTest if installed. Run `php artisan migrate` against your normal LOCAL development database to apply any pending earlier migrations. Verify actual bookings, signed links, Stripe TEST payments and check-in locally.

Push source plus your normal built-asset workflow to GitHub, then deploy using your existing VPS routine. On the VPS run the required earlier migrations (`php artisan migrate --force` after backup), clear/cache configuration as appropriate, cache routes/views, ensure storage linking and restart existing queue workers. Verify the scheduler. Do not regenerate APP_KEY on an existing app. No VPS path is assumed.

Review the route removals in the report: they were unfinished methods, not working features. Remote URL imports now reject redirects/internal networks; use a final public URL. PHP cURL and DOM/XML extensions are needed for the new fetch/sanitization behavior.

The uploaded files alone cannot prove production readiness. The report distinguishes completed source checks from checks you still need to run in your full application.
