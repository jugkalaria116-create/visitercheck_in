# API reference

All responses are JSON. Success: `{ "data": ..., "meta": ... }`. Errors: `{ "error": { "message", "code", "fields": {field: message} } }`.
Dates are ISO-8601 UTC strings generated from BSON `UTCDateTime`. Admin routes use the `vms_session` cookie (HttpOnly, SameSite=Lax) and require the header `X-CSRF-Token` on every non-GET request (obtain it from `GET /api/admin/me`).

## Public

| Method & path | Description |
|---|---|
| `GET /api/health` | `{status, database, time}`; 503 if MongoDB is unreachable. No secrets. |
| `GET /api/public/config` | `registration_enabled, phone_country(_name), polling_interval_seconds, max_upload_bytes, display_duration_seconds` |
| `POST /api/visitors` | `multipart/form-data`: `name`, `phone`, `consent=1`, `photo` (JPEG/PNG/WebP), optional `submission_id` (idempotency key). 201 → `{id,name,expires_at,duration_seconds}`. 422 with `fields`, 403 `registration_closed`, 429 rate-limited (20 / 10 min / IP). |
| `GET /api/display/current` | Authoritative state (see below). Performs due expiry/rotation on the server. `Cache-Control: no-store`. |
| `POST /api/display/heartbeat` | JSON `{client_token, name?, version?, mode?}`. Upserts the display client (token stored only as SHA-256). |
| `GET /api/media/visitor/:id` | Photo — public only while that visitor is on screen; admins can fetch any. `no-store`. |
| `GET /api/media/gallery/:id` | Gallery image — public only if enabled. ETag + 60 s private cache. |

`GET /api/display/current` → 
```json
{"data":{"mode":"visitor|random_image|branded","version":42,"visitor_name":"Jane","image_url":"/api/media/visitor/…?v=42",
 "expires_at":"2026-11-03T10:05:00.000Z","started_at":"…","server_time":"…","polling_interval_seconds":2}}
```
`branded` = no visitor and no usable gallery image (stored mode is still `random_image` with `image_id: null`). `expires_at` is the visitor's end time, or the next gallery rotation (null if ≤1 image).

## Admin auth

| | |
|---|---|
| `GET /api/admin/me` | `{authenticated, admin, csrf_token}` (also starts the session so a CSRF token exists before login) |
| `POST /api/admin/login` | `{username,password}`. 15 attempts/15 min/IP; account locks 15 min after 5 failures; session id regenerated. |
| `POST /api/admin/logout` | |

## Admin data (session + CSRF)

| | |
|---|---|
| `GET /api/admin/stats` | dashboard figures computed from MongoDB |
| `GET /api/admin/visitors?q=&status=active\|completed&from=YYYY-MM-DD&to=&page=&per_page=` | paginated list (includes phone) |
| `GET /api/admin/visitors/:id` · `DELETE …` | detail · delete record **and** photo file |
| `GET /api/admin/gallery` · `POST` (multipart `images[]`, ≤20) | list · upload |
| `PATCH /api/admin/gallery/:id` `{title?, enabled?}` · `DELETE` | |
| `GET /api/admin/settings` · `PATCH` | partial update; `apply_to_current: true` re-times the running visitor |
| `GET /api/admin/displays` · `DELETE /api/admin/displays/:id` | TV clients with `online / recently_disconnected / offline` |
| `GET /api/admin/qr-code?format=svg\|png&download=1` | QR of `settings.registration_url` |

Status codes: 200/201, 304, 400 bad JSON, 401 unauthenticated, 403 CSRF/closed, 404, 405, 422 validation, 429 rate limit, 503 database down, 500 generic (no stack traces; details only in the server log).

## Settings fields

`display_duration_seconds` (10–86400, default 300) · `polling_interval_seconds` (1–30) · `gallery_rotation_seconds` (5–3600) · `fallback_mode` (`random_image`|`branded`) · `registration_enabled` · `registration_url` · `phone_country` (`IN, US, CA, GB, AU, AE, ANY`) · `visitor_retention_days` (0–3650, 0 = forever).

## Extra collections

Besides the six required collections the app uses `rate_limits` (TTL-indexed counters) so rate limiting works across PHP processes.
