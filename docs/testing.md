# Test plan

## Automated (run on your machine)

```powershell
cd backend
php scripts\test-display-engine.php                 # needs MongoDB; uses a throw-away DB
php scripts\smoke-test.php http://localhost:8000    # needs the server running
```
`test-display-engine.php` covers: phone validation, branded fallback, gallery rotation, visitor replacing visitor, version bump, previous visitor completed, refresh does not move `expires_at`, server-side expiry, **stale-expiry race (a newer visitor is never overwritten)**, deleting the on-screen visitor, disabling all images, settings persistence.

## Manual acceptance checklist

Use a phone (on the same network, ideally HTTPS) and two or more browser windows/TVs on `/display`.

| # | Criterion | How to check | ✓ |
|---|---|---|---|
| 1 | Scan QR → form on phone | Print/scan the QR from Admin → QR code | |
| 2 | Name + phone | Try empty / invalid phone → inline errors; valid → accepted | |
| 3 | Live photo or upload | Both paths; retake; deny camera permission → upload still works | |
| 4 | Saved | Visitor appears in Admin → Visitors with thumbnail | |
| 5 | All TVs show visitor | Two `/display` windows both switch within ~2 s | |
| 6 | New replaces old | Register A then B within a minute → B on all TVs, A "Shown" | |
| 7 | Server-controlled 5 min | Set duration 30 s in Settings, register, watch it expire | |
| 8 | Gallery after expiry | Enabled gallery images appear; rotate at the configured interval | |
| 9 | Refresh keeps timer | Refresh a TV mid-visit → same visitor, same expiry (Dashboard shows `expires_at`) | |
| 10 | TVs in sync | Compare windows after several transitions; `version` equal in Admin → TV displays | |
| 11 | Admin login/manage | Wrong password → error; 5 failures → lock; delete a visitor | |
| 12 | Gallery CRUD | Upload many, rename, disable (disappears from TVs), delete | |
| 13 | Settings | Change duration (next visitor uses it), disable registration (form shows "closed") | |
| 14 | QR download | PNG + SVG download; scans correctly when printed | |
| 15 | Real stats | Dashboard numbers match the Visitors table; TV counts match open TVs | |
| 16 | Unauthorized | Signed out: `/api/admin/visitors` → 401; `/api/media/visitor/<old id>` → 404 | |
| 17 | MongoDB only | No other DB configured anywhere | |
| 18 | Docs work | Fresh clone follows `installation.md` end-to-end | |

## Extra scenarios

* **Offline TV:** disconnect a TV's network → it keeps the last scene but **never shows a visitor past expiry** (switches to the branded screen); reconnect → it reconciles within a few seconds with backoff. The amber dot (bottom-right) shows reconnecting.
* **Server restart:** stop/start PHP and MongoDB while TVs are open → TVs recover on their own; state and timers persist in MongoDB.
* **Concurrent registrations:** two phones submit at the same moment → the last committed registration is on screen, the other is "Shown"; no error, no stuck state.
* **Duplicate submit:** double-tap *Check in* → one record (button disabled + server idempotency key).
* **Uploads:** `.php` renamed to `.jpg`, a 20 MB file, a 50×50 image, a corrupted JPEG, an SVG → all rejected with a clear message.
* **Retention:** set retention to 1 day, backdate `retention_expires_at` in MongoDB, run `php scripts\purge-expired.php` → record and file in `storage/visitors` are gone.
