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
| `GET` | `.../tracker/{id}` | Fetch a single ticket (includes all replies) |
| `POST` | `.../tracker/` | Create a new ticket |
| `PATCH` | `.../tracker/{id}` | Partial update (only supplied fields) |
| `PUT` | `.../tracker/{id}` | Full replace |
| `DELETE` | `.../tracker/{id}` | Delete a ticket |
| `GET` | `.../tracker/{id}/replies/` | List all replies on a ticket |
| `GET` | `.../tracker/{id}/replies/{reply_id}` | Fetch a single reply |
| `POST` | `.../tracker/{id}/replies/` | Add a reply to a ticket |
| `PUT` | `.../tracker/{id}/replies/{reply_id}` | Replace a reply |
| `PATCH` | `.../tracker/{id}/replies/{reply_id}` | Partially update a reply |
| `DELETE` | `.../tracker/{id}/replies/{reply_id}` | Delete a reply |

> **Filter parameters** are not yet exposed through this REST API (the `GET .../tracker/` list
> always returns everything the user can see, up to 500 tickets).
> Attachments are accessible via the standard Links/Attachments facility described in
> [Links-and-attachments.md](Links-and-attachments.md).

---

## 3. Ticket JSON Object

This is the canonical shape returned by GET and accepted by POST / PUT / PATCH.

```json
{
  "@type":      "Ticket",
  "id":         42,
  "title":      "Login page crashes on mobile",
  "description": "Steps to reproduce: ...",
  "tracker":    8,
  "status":     "Open",
  "priority":   5,
  "privacy":    "public",
  "category":   "Bug",
  "resolution": null,
  "creator":    "demo@example.org",
  "created":    "2026-05-25T10:00:00+00:00",
  "updated":    "2026-05-25T11:30:00+00:00",
  "closed":     null
}
```

### Field Reference

| Field | Type | Writable | Description |
|-------|------|----------|-------------|
| `@type` | `"Ticket"` | No | Always `"Ticket"`. Ignored on write. |
| `id` | integer | No | Ticket ID. Auto-assigned on POST. |
| `title` | string | Yes | **Required on POST/PUT.** One-line summary. |
| `description` | string | Yes | Full description. Omitted from the response when empty. |
| `tracker` | integer | Yes | The queue the ticket belongs to, and what makes a queue-specific `category`/`version`/`status`/`resolution` label valid. Defaults to the caller's first accessible queue. |
| `status` | string | Yes | Capitalized status label. See [Status Values](#6-status-values). |
| `priority` | integer (1–9) | Yes | See [Priority Values](#7-priority-values). |
| `privacy` | `"public"` \| `"private"` | Yes | `"private"` = visible only to creator, assignees and tracker admins. |
| `category` | string | Yes | Admin-managed label, scoped to the ticket's `tracker`. Omitted when not set. |
| `resolution` | string | Yes | Admin-managed label, scoped to the ticket's `tracker`. Omitted when not set. |
| `creator` | string | No | Account email of whoever created the ticket. |
| `participants` | object | Yes | JSCalendar-style map keyed by account id / e-mail — creator has role `owner`, assignees `attendee`. |
| `group` | string | Yes | Account email of the responsible group. Omitted when not set. |
| `created` | ISO 8601 datetime | No | Auto-set on creation. |
| `updated` | ISO 8601 datetime | No | Auto-set on every save. Omitted until the ticket is first modified. |
| `closed` | ISO 8601 datetime | No | Auto-set when status → `"Closed"`. Omitted when not set. |

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
      "title":    "Fix login crash",
      "status":   "Open",
      "priority": 5,
      "privacy":  "public",
      "created":  "2026-05-20T15:55:24+00:00",
      "updated":  "2026-05-20T16:15:18+00:00"
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
The response always includes a `replies` object whose keys are reply IDs (as strings) - see
[§14 Replies Sub-Resource](#14-replies-sub-resource). If a ticket has no replies, the `replies`
key is absent. Restricted replies (`"restricted": true`) are only included when the authenticated
user is an admin, technician, or assignee of the queue.

**Response `200 OK`:**

```json
{
  "@type":       "Ticket",
  "id":          42,
  "title":       "Login page crashes on mobile",
  "description": "Steps to reproduce:\n1. Open mobile browser\n2. Navigate to /login\n3. Crash",
  "status":      "Open",
  "priority":    5,
  "privacy":     "public",
  "created":     "2026-05-25T10:00:00+00:00",
  "updated":     "2026-05-25T11:30:00+00:00",
  "replies": {
    "101": {
      "@type":      "Reply",
      "id":         101,
      "message":    "Reproduced on Chrome/Android. Assigning to mobile team.",
      "creator":    "admin",
      "created":    "2026-05-25T12:00:00+00:00",
      "restricted": false
    }
  }
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

Status strings are **capitalized** in the API (the queue's own status label, title-cased for the stock statuses).

| String value | Internal code | Description |
|---|---|---|
| `"Open"` | `-100` | Active, unresolved ticket |
| `"Closed"` | `-101` | Resolved/completed ticket |
| `"Deleted"` | `-102` | Soft-deleted (not shown in normal lists) |
| `"Pending"` | `-103` | Waiting for external input |

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
  "status":      "Open",
  "priority":    7,
  "privacy":     "public"
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
  "status":   "Closed",
  "priority": 3
}
```

**Response `204 No Content`** — no body.

**Behaviour notes:**
- `title`, `tracker`, `status`, `priority`, `privacy`, `category`, `version` and `resolution` are
  accepted, subject to the per-field rights below.
- **`description` is not.** Its stock field ACL is `TRACKER_ITEM_NEW`
  (`tracker_bo::$field_acl['tr_description']`), i.e. it is writable only while the ticket is being
  created — a `PATCH` still answers `204` and leaves the description unchanged, for admins too.
  Add a [reply](#12-replies) instead of trying to append to it.
- Fields the authenticated user cannot modify (based on their role in the queue) are **silently
  skipped** — the response is `204` either way, so read the ticket back if it matters.

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
  "status":      "Open",
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
    "status":   "Open"
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
  -d '{"status": "Closed", "title": "Fixed: Button not working in Firefox"}' \
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
  -d '{"status":"Closed"}' -w "PATCH: HTTP %{http_code}\n"

# Delete
curl -sk -u "$AUTH" "$BASE/$ID" -X DELETE -w "DELETE: HTTP %{http_code}\n"
```

---

## 13. Replies Sub-Resource

Each ticket can have one or more **replies** (comments / notes). Replies appear as a child
collection at `/tracker/{id}/replies/`, and are also included inline on every single-ticket GET
(see [§5](#5-get--single-ticket)).

### Reply JSON Object

```json
{
  "@type":      "Reply",
  "id":         101,
  "message":    "Can you provide more details?",
  "creator":    "admin",
  "created":    "2026-05-26T09:00:00+00:00",
  "restricted": false
}
```

| Field | Type | Writable | Description |
|-------|------|----------|-------------|
| `@type` | `"Reply"` | No | Always `"Reply"`. |
| `id` | integer | No | Reply ID. Auto-assigned on POST. |
| `message` | string | Yes | **Required on POST/PUT.** The reply text. |
| `creator` | string | No | Auto-set to the authenticated user on creation. |
| `created` | ISO 8601 datetime | No | Auto-set on creation. |
| `restricted` | boolean | Yes | `true` = visible only to admins, technicians, and assignees. Default `false`. |

### ACL rules

| Operation | Who can perform it |
|-----------|---------------------|
| GET (read) | Anyone who can read the ticket (same as ticket read ACL) |
| POST (create) | Anyone who can read the ticket |
| PUT/PATCH (update) | The reply's **creator** OR queue admin/technician |
| DELETE | The reply's **creator** OR queue admin/technician |

### List replies

```
GET /egroupware/groupdav.php/{user}/tracker/{id}/replies/
Accept: application/json
```

Returns a JSON object whose keys are reply IDs (as strings):

```json
{
  "101": { "@type": "Reply", "id": 101, "message": "First reply", "creator": "admin", "created": "2026-05-26T09:00:00+00:00", "restricted": false },
  "102": { "@type": "Reply", "id": 102, "message": "Staff-only note", "creator": "techuser", "created": "2026-05-26T10:00:00+00:00", "restricted": true }
}
```

### Fetch a single reply

```
GET /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
Accept: application/json
```

Returns the single Reply object, or `404 Not found` if it doesn't exist or isn't visible to the
authenticated user.

### Create a reply (POST)

```
POST /egroupware/groupdav.php/{user}/tracker/{id}/replies/
Content-Type: application/json
```

```json
{ "message": "I can reproduce this. Working on a fix." }
```

Or with a restricted (staff-only) note:

```json
{ "message": "Internal: do NOT close yet - waiting for customer confirmation.", "restricted": true }
```

**Response `201 Created`:**

```
Location: /egroupware/groupdav.php/admin/tracker/42/replies/101
```

No body is returned. The new reply ID is in the `Location` header.

### Update a reply (PUT / PATCH)

```
PUT   /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
PATCH /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
Content-Type: application/json
```

PUT replaces the reply fully (`message` required); PATCH applies only the supplied fields:

```json
{ "message": "Updated reply text." }
```

```json
{ "restricted": true }
```

**Response `204 No Content`** - no body.

### Delete a reply

```
DELETE /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
```

**Response `204 No Content`** - reply deleted.

### curl examples - replies

```bash
BASE="https://example.egroupware.org/egroupware/groupdav.php/admin/tracker"
AUTH="admin:YOUR_APP_PASSWORD"
TICKET_ID=42

# List all visible replies
curl -sk -u "$AUTH" "$BASE/$TICKET_ID/replies/" -H "Accept: application/json" | python3 -m json.tool

# Add a public reply
LOC=$(curl -si -u "$AUTH" "$BASE/$TICKET_ID/replies/" -X POST \
  -H "Content-Type: application/json" \
  -d '{"message":"Working on a fix now."}' \
  | grep -i "^location:" | tr -d '\r\n')
REPLY_ID=$(echo "$LOC" | sed 's|.*/replies/||' | tr -d '/ \r\n')
echo "Created reply ID=$REPLY_ID"

# Fetch that reply
curl -sk -u "$AUTH" "$BASE/$TICKET_ID/replies/$REPLY_ID" -H "Accept: application/json"

# Edit the reply text (PATCH)
curl -sk -u "$AUTH" "$BASE/$TICKET_ID/replies/$REPLY_ID" -X PATCH \
  -H "Content-Type: application/json" \
  -d '{"message":"Fixed in commit abc123."}' -w "PATCH: HTTP %{http_code}\n"

# Delete the reply
curl -sk -u "$AUTH" "$BASE/$TICKET_ID/replies/$REPLY_ID" -X DELETE -w "DELETE: HTTP %{http_code}\n"
```

---

## 14. Attachments

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
