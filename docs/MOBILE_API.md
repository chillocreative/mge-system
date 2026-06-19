# MGE-PMS — Mobile API Integration Guide

This backend is ready for a native mobile client (e.g. **Flutter**). The web app
uses cookie/session auth; mobile clients use **Sanctum Bearer tokens**. Once a
client holds a token, **every** `/api/*` endpoint works by sending the header:

```
Authorization: Bearer <token>
Accept: application/json
```

No CSRF token and no cookies are required for token auth.

---

## Base URL

| Environment | Base URL |
|-------------|----------|
| Production  | `https://app.mge-eng.com/api` |
| Local dev   | `http://mge-pms.test/api` |

## Standard response envelope

Every endpoint returns:

```json
{ "success": true, "message": "…", "data": { … } }
```

Errors: `{ "success": false, "message": "…", "errors": { "field": ["…"] } }`
with HTTP `422` (validation), `401` (unauthenticated), `403` (no permission),
`404` (not found), `429` (rate limited).

---

## Authentication flow (mobile)

### 1. Health / version probe (public)
`GET /api/health`
```json
{ "success": true, "data": { "status": "ok", "app": "MGE-PMS", "api_version": "v1", "time": "…" } }
```
Use this for connectivity checks and future force-update gating.

### 2. Login → get a token (public, throttled 6/min)
`POST /api/auth/login`
```json
{ "email": "user@mge-eng.com", "password": "secret", "device_name": "Pixel 8 - Flutter" }
```
Response:
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "full_name": "…", "email": "…", "roles": ["Admin & HR"], "permissions": ["projects.view", "…"] },
    "token": "1|xxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```
Store the `token` securely (e.g. `flutter_secure_storage`). The `roles` and
`permissions` arrays let you gate the mobile UI exactly like the web app does.

> Non-active accounts (pending/rejected/suspended) get a `422` with a reason —
> the same rules as the web login.

### 3. Register (public, throttled) — pending approval
`POST /api/auth/register` — body: `first_name, last_name, email, password,
password_confirmation, phone?`. New users start `pending` until an admin approves.

### 4. Current user (authenticated)
`GET /api/auth/me` → returns the `UserResource` (with `roles` + `permissions`).
Call on app start to refresh permissions.

### 5. Logout (authenticated)
- `POST /api/auth/logout` — revokes the **current device's** token.
- `POST /api/auth/logout-all` — revokes tokens on **all devices**.

Tokens do not expire by default (configurable via `config/sanctum.php`
`expiration`); rely on logout to revoke.

---

## Using the rest of the API

All existing endpoints accept the Bearer token. Examples:
`GET /api/dashboard`, `GET /api/projects`, `GET /api/tasks`,
`GET /api/employees`, `GET /api/leaves`, `GET /api/payroll`,
`GET /api/project-invoices?project_id=1`, etc. Lists are paginated; pass
`?page=` and `?per_page=` (capped at 100). Filter params are documented per
controller (most accept `search`, `status`, and a resource-specific id).

### Permission gating
Gate screens/actions on the `permissions` array from login/`me`. Server-side
every route is still enforced with `permission:<name>` middleware, so a missing
permission returns `403` regardless of the client.

### File uploads & downloads
- Uploads: send `multipart/form-data` with the Bearer header (no `_method`
  spoofing needed on native HTTP clients that support real `PUT`/`POST`).
- Downloads (documents, payslips, attachments, employee photos) are streamed
  from authenticated endpoints like `GET /api/.../download` — send the Bearer
  header and save the response bytes.

### Real-time (optional, Pusher)
Private/presence channels authorize via `POST /api/broadcasting/auth` with the
Bearer header. Use the public `PUSHER_APP_KEY` + cluster in the Flutter Pusher
client; never embed the Pusher secret in the app.

---

## Minimal Dart example

```dart
final res = await http.post(
  Uri.parse('https://app.mge-eng.com/api/auth/login'),
  headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
  body: jsonEncode({'email': email, 'password': password, 'device_name': deviceName}),
);
final token = jsonDecode(res.body)['data']['token'];

// subsequent calls
final projects = await http.get(
  Uri.parse('https://app.mge-eng.com/api/projects'),
  headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
);
```

---

## Notes / future hardening
- CORS (`config/cors.php`) only affects browsers; native apps are unaffected.
- Consider scoping token abilities (`createToken($name, [$abilities])`) if you
  later want read-only or feature-limited mobile tokens.
- Rate limits: auth endpoints are throttled (6/min). Add `throttle:api` broadly
  if mobile traffic grows.
- For production, switch `MAIL_MAILER` off `log` so password-reset/notification
  emails actually send.
