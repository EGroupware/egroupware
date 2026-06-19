# Tracker REST API

A **CRUD REST API** for the EGroupware **Tracker** app (bug/issue tracker), exposed via the
GroupDAV endpoint alongside Addressbook, Calendar, Infolog, and Timesheet.

---

## 1. Base URL & Authentication

```
https://example.egroupware.org/egroupware/groupdav.php/{user}/tracker/
```

| Part | Description |
|------|-------------|
| `{user}` | EGroupware username (e.g. `admin`, `sysop`). Scopes the collection to that user's tickets. |

**Required headers for JSON:**

| Request type | Header |
|---|---|
| All reads | `Accept: application/json` |
| POST / PUT / PATCH | `Content-Type: application/json` |

---

## 2. Endpoints Overview

| Method | URL | Description |
|--------|-----|-------------|
| `GET` | `.../tracker/` | List all accessible tickets (up to 500) |
| `GET` | `.../tracker/{id}` | Fetch a single ticket |
| `POST` | `.../tracker/` | Create a new ticket |
| `PATCH` | `.../tracker/{id}` | Partial update (only supplied fields) |
| `PUT` | `.../tracker/{id}` | Full replace |
| `DELETE` | `.../tracker/{id}` | Delete a ticket |

> **Replies/comments** and **filter parameters** are not yet exposed through this REST API.
> Attachments are accessible via the standard Links/Attachments facility described in
> [Links-and-attachments.md](Links-and-attachments.md).

---

## 3. Ticket JSON Object

This is the canonical shape returned by GET and accepted by POST / PUT / PATCH.

```json
{
  "@type":      "Ticket",
  "id":         42,
  "uid":        "42",
  "title":      "Login page crashes on mobile",
  "description": "Steps to reproduce: ...",
  "status":     "open",
  "priority":   5,
  "private":    false,
  "created":    "2026-05-25T10:00:00+00:00",
  "modified":   "2026-05-25T11:30:00+00:00",
  "closed":     null
}
```

### Field Reference

| Field | Type | Writable | Description |
|-------|------|----------|-------------|
| `@type` | `"Ticket"` | No | Always `"Ticket"`. Ignored on write. |
| `id` | integer | No | Ticket ID. Auto-assigned on POST. |
| `uid` | string | No | Stable identifier — the caldav_name if the ticket was created via REST, otherwise the numeric ID as a string. |
| `title` | string | Yes | **Required on POST/PUT.** One-line summary. |
| `description` | string | Yes | Full description. Omitted from the response when empty. |
| `status` | string | Yes | Lowercase status string. See [Status Values](#6-status-values). |
| `priority` | integer (1–9) | Yes | See [Priority Values](#7-priority-values). |
| `private` | boolean | Yes | `true` = visible only to creator and admins. |
| `created` | ISO 8601 datetime | No | Auto-set on creation. Omitted when not available. |
| `modified` | ISO 8601 datetime | No | Auto-set on every save. Omitted when not available. |
| `closed` | ISO 8601 datetime | No | Auto-set when status → `closed`. Omitted when not set. |

---

## 4. GET — List Tickets

```
GET /egroupware/groupdav.php/{user}/tracker/
Accept: application/json
```

Returns a JSON object keyed by the ticket's path. Each value is a ticket object.
The collection is limited to 500 tickets and no filter parameters are supported.

**Response `200 OK`:**

```json
{
  "responses": {
    "/admin/tracker/2": {
      "@type":    "Ticket",
      "id":       2,
      "uid":      "2",
      "title":    "Fix login crash",
      "status":   "open",
      "priority": 5,
      "private":  false,
      "created":  "2026-05-20T15:55:24+00:00",
      "modified": "2026-05-20T16:15:18+00:00"
    },
    "/admin/tracker/3": { "..." : "..." }
  }
}
```

---

## 5. GET — Single Ticket

```
GET /egroupware/groupdav.php/{user}/tracker/{id}
Accept: application/json
```

Returns the full ticket object directly (not wrapped in `responses`).

**Response `200 OK`:**

```json
{
  "@type":       "Ticket",
  "id":          42,
  "uid":         "42",
  "title":       "Login page crashes on mobile",
  "description": "Steps to reproduce:\n1. Open mobile browser\n2. Navigate to /login\n3. Crash",
  "status":      "open",
  "priority":    5,
  "private":     false,
  "created":     "2026-05-25T10:00:00+00:00",
  "modified":    "2026-05-25T11:30:00+00:00"
}
```

**Response headers:**

```
ETag: "42:1748167200"
Content-Type: application/json; charset=utf-8
```

**Access control:** Private tickets are only visible to the creator, assignees, and tracker admins.

---

## 6. Status Values

Status strings are **lowercase** in the API.

| String value | Internal code | Description |
|---|---|---|
| `"open"` | `-100` | Active, unresolved ticket |
| `"closed"` | `-101` | Resolved/completed ticket |
| `"deleted"` | `-102` | Soft-deleted (not shown in normal lists) |
| `"pending"` | `-103` | Waiting for external input |

---

## 7. Priority Values

Priority is an **integer from 1 (lowest) to 9 (highest)**. Default stock labels:

| Value | Label |
|-------|-------|
| `1` | 1 - lowest |
| `2` | 2 |
| `3` | 3 |
| `4` | 4 |
| `5` | 5 - medium |
| `6` | 6 |
| `7` | 7 |
| `8` | 8 |
| `9` | 9 - highest |

> Queue admins can customize priority labels per queue. The API always accepts and returns the **integer value** regardless of custom label configuration.

---

## 8. POST — Create Ticket

```
POST /egroupware/groupdav.php/{user}/tracker/
Content-Type: application/json
Accept: application/json
```

**Minimum request body (`title` is the only required field):**

```json
{ "title": "New bug report" }
```

**Full request body:**

```json
{
  "title":       "Login page crashes on mobile",
  "description": "Steps to reproduce:\n1. Open mobile browser\n2. Navigate to /login\n3. Crash",
  "status":      "open",
  "priority":    7,
  "private":     false
}
```

**Response `201 Created`:**

```
Location: /egroupware/groupdav.php/admin/tracker/42
ETag: "42:1748167200"
```

No body is returned. The new ticket ID is extracted from the `Location` header (last path segment).

---

## 9. PATCH — Partial Update

```
PATCH /egroupware/groupdav.php/{user}/tracker/{id}
Content-Type: application/json
```

Only the fields present in the request body are updated. All other fields retain their current values.

**Request body:**

```json
{
  "title":    "Updated title",
  "status":   "closed",
  "priority": 3
}
```

**Response `204 No Content`** — no body.

**Behaviour notes:**
- Only `title`, `description`, `status`, `priority`, and `private` are accepted.
- Fields the authenticated user cannot modify (based on their role in the queue) are **silently skipped**.

---

## 10. PUT — Full Replace

```
PUT /egroupware/groupdav.php/{user}/tracker/{id}
Content-Type: application/json
```

Replaces the ticket with the supplied body. Fields not included in the body are reset to their defaults. **`title` is required.**

**Request body:**

```json
{
  "title":       "Replaced title",
  "description": "Full replacement description",
  "status":      "open",
  "priority":    5
}
```

**Response `204 No Content`** — no body.

**ETag precondition (optimistic locking):**

```
If-Match: "42:1748167200"
```

If the ticket was modified since the ETag was fetched, the server returns `412 Precondition Failed`.
The same `If-Match` precondition is honored on **PATCH** and **DELETE** as well.

---

## 11. DELETE — Delete Ticket

```
DELETE /egroupware/groupdav.php/{user}/tracker/{id}
```

**Response `204 No Content`** — ticket deleted, no body.

Only tracker admins (full EGroupware admin or tracker `admin` ACL right) may delete tickets.

---

## 12. curl Examples

### List all tickets

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/" \
  -H "Accept: application/json" | python3 -m json.tool
```

### Create a ticket

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/" \
  -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title":    "Button not working in Firefox",
    "priority": 7,
    "status":   "open"
  }' -i | grep -E "HTTP|Location"
```

### Fetch a single ticket

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42" \
  -H "Accept: application/json" | python3 -m json.tool
```

### Update status and title (PATCH)

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42" \
  -X PATCH \
  -H "Content-Type: application/json" \
  -d '{"status": "closed", "title": "Fixed: Button not working in Firefox"}' \
  -w "HTTP %{http_code}\n"
```

### Delete a ticket

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42" \
  -X DELETE \
  -w "HTTP %{http_code}\n"
```

### Full-cycle example (create → update → delete)

```bash
BASE="https://example.egroupware.org/egroupware/groupdav.php/admin/tracker"
AUTH="admin:YOUR_APP_PASSWORD"

# Create
LOC=$(curl -si -u "$AUTH" "$BASE/" -X POST \
  -H "Content-Type: application/json" \
  -d '{"title":"Test ticket","priority":3}' \
  | grep -i "^location:" | tr -d '\r\n')
ID=$(echo "$LOC" | sed 's|.*tracker/||' | tr -d '/ \r\n')
echo "Created ticket ID=$ID"

# Read
curl -sk -u "$AUTH" "$BASE/$ID" -H "Accept: application/json" | python3 -m json.tool

# Update
curl -sk -u "$AUTH" "$BASE/$ID" -X PATCH \
  -H "Content-Type: application/json" \
  -d '{"status":"closed"}' -w "PATCH: HTTP %{http_code}\n"

# Delete
curl -sk -u "$AUTH" "$BASE/$ID" -X DELETE -w "DELETE: HTTP %{http_code}\n"
```

---

## 13. Attachments

Ticket attachments are accessible through EGroupware's **Links and Attachments** facility.
See [Links-and-attachments.md](Links-and-attachments.md) for the complete reference.

The links sub-collection for a ticket is at:

```
/egroupware/groupdav.php/{user}/tracker/{id}/links/
```

### List attachments

```bash
curl -sk -u "admin:PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/" \
  -H "Accept: application/json" | python3 -m json.tool
```

### Upload an attachment (POST multipart)

```bash
curl -sk -u "admin:PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/" \
  -X POST \
  -F "file=@/path/to/screenshot.png;type=image/png" \
  -i | grep -E "HTTP|Location"
```

### Upload an attachment as raw bytes (PUT)

```bash
curl -sk -u "admin:PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/screenshot.png" \
  -X PUT \
  -H "Content-Type: image/png" \
  --data-binary "@/path/to/screenshot.png" \
  -w "HTTP %{http_code}\n"
```

### Delete an attachment

```bash
curl -sk -u "admin:PASSWORD" \
  "https://example.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/{link_id}" \
  -X DELETE -w "HTTP %{http_code}\n"
```
