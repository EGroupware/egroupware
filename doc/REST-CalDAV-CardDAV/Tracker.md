# Tracker REST API

A full **CRUD REST API** for the EGroupware **Tracker** app (bug/issue tracker), exposed via the
GroupDAV endpoint alongside Addressbook, Calendar, Infolog, and Timesheet.

---

## 1. Base URL & Authentication

```
https://example.egroupware.org/egroupware/groupdav.php/{user}/tracker/
```

| Part   | Description |
|--------|-------------|
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
| `GET` | `.../tracker/` | List all accessible tickets |
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

---

## 3. Ticket JSON Object

This is the canonical shape returned by GET and accepted by POST / PUT / PATCH.

```json
{
  "@type":       "Ticket",
  "id":          42,
  "summary":     "Login page crashes on mobile",
  "description": "Steps to reproduce: ...",
  "tracker":     8,
  "status":      "Open",
  "priority":    5,
  "completion":  0,
  "startDate":   "2026-05-25T00:00:00Z",
  "dueDate":     "2026-06-01T00:00:00Z",
  "closed":      null,
  "private":     false,
  "category":    "Bug",
  "version":     null,
  "creator":     "admin",
  "created":     "2026-05-25T10:00:00Z",
  "modified":    "2026-05-25T11:30:00Z",
  "modifier":    "admin",
  "assigned": [
    { "uid": "urn:ietf:params:scim:schemas:core:2.0:User:admin" }
  ],
  "cc":   null,
  "group": "Admins",
  "egroupware.org:customfields": {},
  "etag": "42:1748167200"
}
```

### Field Reference

| Field | Type | Writable | Description |
|-------|------|----------|-------------|
| `@type` | `"Ticket"` | No | Always `"Ticket"`. Ignored on write. |
| `id` | integer | No | Ticket ID. Auto-assigned on POST. |
| `summary` | string | Yes | **Required on POST/PUT.** One-line title. |
| `description` | string | Yes | Full description. Only settable on creation (read-only on PATCH/PUT for non-admins). |
| `tracker` | integer | Yes | Queue/category ID. Auto-set to first available queue if omitted on POST. |
| `status` | string | Yes | See [Status Values](#11-status-values). |
| `priority` | integer (1–9) | Yes | See [Priority Values](#12-priority-values). |
| `completion` | integer (0–100) | Yes* | Percentage complete. *Requires assignee or admin role. |
| `startDate` | UTC datetime | Yes | ISO 8601, e.g. `"2026-05-25T00:00:00Z"`. |
| `dueDate` | UTC datetime | Yes | ISO 8601. |
| `closed` | UTC datetime | No | Auto-set when status → Closed. |
| `private` | boolean | Yes | `true` = visible only to creator and admins. |
| `category` | string/int | Yes | EGroupware category name or ID. |
| `version` | string/int | Yes | Version category name or ID. |
| `creator` | string | Yes* | Username. Auto-set to authenticated user on POST. |
| `created` | UTC datetime | No | Auto-set on creation. |
| `modified` | UTC datetime | No | Auto-set on every save. |
| `modifier` | string | No | Auto-set on every save. |
| `assigned` | array | Yes | Array of `{ "uid": "urn:ietf:params:scim:...User:{login}" }` objects. |
| `cc` | string | Yes | Comma-separated email addresses for CC notifications. |
| `group` | string | Yes* | Responsible group name. Requires technician or admin role. |
| `egroupware.org:customfields` | object | Yes | Custom field key → value map. |
| `etag` | string | No | Used for optimistic concurrency (`If-Match` header). |

---

## 4. GET — List Tickets

```
GET /egroupware/groupdav.php/{user}/tracker/
Accept: application/json
```

Returns a JSON object keyed by the ticket's path. Each value is a ticket object.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `nresults` | integer | Limit the number of results (default: unlimited). |
| `filters[search]` | string | Full-text search across summary and description. |
| `filters[status]` | string | Filter by status label (`Open`, `Closed`, `Pending`). |
| `filters[priority]` | integer | Filter by priority (1–9). |
| `filters[tracker]` | integer | Filter by queue/category ID. |
| `filters[assigned]` | string/int | Filter by assigned user (login name or account ID). |
| `filters[linked]` | string | Filter by linked record, format: `"<app>:<id>"` e.g. `"infolog:5"`. |
| `filters[#cf_name]` | string | Filter by custom field value (prefix name with `#`). |

**Response `200 OK`:**

```json
{
  "responses": {
    "/admin/tracker/2": {
      "@type": "Ticket",
      "id": 2,
      "summary": "Fix login crash",
      "status": "Open",
      "priority": 5,
      "creator": "admin",
      "created": "2026-05-20T15:55:24Z",
      "modified": "2026-05-20T16:15:18Z",
      "modifier": "admin",
      "group": "Admins",
      "etag": "2:1779293718",
      "private": false
    },
    "/admin/tracker/3": { ... }
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
The response always includes a `replies` object whose keys are reply IDs (as strings).

**Response `200 OK`:**

```json
{
  "@type": "Ticket",
  "id": 42,
  "summary": "Login page crashes on mobile",
  "status": "Open",
  "priority": 5,
  "creator": "admin",
  "created": "2026-05-25T10:00:00Z",
  "private": false,
  "etag": "42:1748167200",
  "replies": {
    "101": {
      "@type":      "Reply",
      "id":         101,
      "message":    "Reproduced on Chrome/Android. Assigning to mobile team.",
      "creator":    "admin",
      "created":    "2026-05-25T12:00:00Z",
      "restricted": false
    },
    "102": {
      "@type":      "Reply",
      "id":         102,
      "message":    "Internal note — only visible to staff.",
      "creator":    "techuser",
      "created":    "2026-05-25T13:30:00Z",
      "restricted": true
    }
  }
}
```

If a ticket has no replies the `replies` key is absent.  
Restricted replies (`"restricted": true`) are only included when the authenticated user is an admin, technician, or assignee of the queue.

**Response headers:**

```
ETag: "42:1748167200"
Content-Type: application/json
```

---

## 6. POST — Create Ticket

```
POST /egroupware/groupdav.php/{user}/tracker/
Content-Type: application/json
Accept: application/json
```

**Minimum request body (only `summary` is required):**

```json
{
  "summary": "New bug report"
}
```

**Full request body:**

```json
{
  "summary":     "Login page crashes on mobile",
  "description": "Steps to reproduce:\n1. Open mobile browser\n2. Navigate to /login\n3. Crash",
  "status":      "Open",
  "priority":    7,
  "startDate":   "2026-05-25T00:00:00Z",
  "dueDate":     "2026-06-01T00:00:00Z",
  "private":     false,
  "assigned": [
    { "uid": "urn:ietf:params:scim:schemas:core:2.0:User:john" }
  ],
  "cc": "manager@example.com"
}
```

**Response `201 Created`:**

```
Location: /egroupware/groupdav.php/admin/tracker/42
ETag: "42:1748167200"
```

No body is returned. The new ticket ID is extracted from the `Location` header (last path segment).

**Auto-defaults on creation:**

| Field | Default |
|-------|---------|
| `tracker` | First available queue the user has access to |
| `creator` | Authenticated user |
| `status` | `Open` |
| `priority` | Queue default (or `5 - medium`) |

---

## 7. PATCH — Partial Update

```
PATCH /egroupware/groupdav.php/{user}/tracker/{id}
Content-Type: application/json
```

Only the fields present in the request body are updated. All other fields retain their current values.

**Request body:**

```json
{
  "summary":  "Updated title",
  "status":   "Closed",
  "priority": 3
}
```

**Response `204 No Content`** — no body.

**Behaviour notes:**
- Fields the authenticated user cannot modify (based on their role in the queue) are **silently skipped** rather than causing an error. This is intentional — the caller cannot always know which fields are read-only for their role.
- `description` is always read-only on PATCH (it can only be set at creation).
- `completion`, `resolution`, `budget` require the user to be an **assignee** or **queue admin**.
- `group` requires **technician** or **queue admin** role.

---

## 8. PUT — Full Replace

```
PUT /egroupware/groupdav.php/{user}/tracker/{id}
Content-Type: application/json
```

Replaces the ticket with the supplied body. Fields not included in the body are reset to their defaults (similar to a full overwrite). Requires the same writable fields as POST.

**`summary` is required.**

**Request body:**

```json
{
  "summary":     "Replaced title",
  "description": "Full replacement",
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

---

## 9. DELETE — Delete Ticket

```
DELETE /egroupware/groupdav.php/{user}/tracker/{id}
```

**Response `204 No Content`** — ticket deleted, no body.

---

## 10. Filter Reference

Filters are passed as query parameters using the `filters[key]=value` pattern.

```
GET /egroupware/groupdav.php/admin/tracker/?filters[status]=Open&filters[priority]=9&nresults=20
```

| Filter key | Example value | Notes |
|------------|---------------|-------|
| `search` | `mobile login` | Full-text search in summary + description |
| `status` | `Open` | One of: `Open`, `Closed`, `Pending`, `Deleted`, or custom queue status |
| `priority` | `9` | Integer 1–9 |
| `tracker` | `8` | Queue ID (from EGroupware Tracker admin) |
| `assigned` | `john` or `15` | Login name or account ID |
| `linked` | `infolog:5` | Only tickets linked to the given record |
| `#cf_fieldname` | `high` | Custom field filter (prefix the field name with `#`) |

---

## 11. Status Values

| String value | Internal code | Description |
|---|---|---|
| `"Open"` | `-100` | Active, unresolved ticket |
| `"Closed"` | `-101` | Resolved/completed ticket |
| `"Deleted"` | `-102` | Soft-deleted (not shown in normal lists) |
| `"Pending"` | `-103` | Waiting for external input |

Custom per-queue statuses can also be configured in the EGroupware Tracker admin. These are returned and accepted as their label string.

---

## 12. Priority Values

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

## 13. curl Examples

### List all open tickets

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/?filters[status]=Open" \
  -H "Accept: application/json" | python3 -m json.tool
```

### Create a ticket

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/" \
  -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "summary":  "Button not working in Firefox",
    "priority": 7,
    "status":   "Open",
    "assigned": [{"uid": "urn:ietf:params:scim:schemas:core:2.0:User:admin"}]
  }' -i | grep -E "HTTP|Location"
```

### Fetch a single ticket

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42" \
  -H "Accept: application/json" | python3 -m json.tool
```

### Update status and summary (PATCH)

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42" \
  -X PATCH \
  -H "Content-Type: application/json" \
  -d '{"status": "Closed", "summary": "Fixed: Button not working in Firefox"}' \
  -w "HTTP %{http_code}\n"
```

### Search tickets by keyword

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/?filters[search]=login&nresults=10" \
  -H "Accept: application/json" | python3 -m json.tool
```

### Delete a ticket

```bash
curl -sk \
  -u "admin:YOUR_APP_PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42" \
  -X DELETE \
  -w "HTTP %{http_code}\n"
```

### Full-cycle example (create → update → delete)

```bash
BASE="https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker"
AUTH="admin:YOUR_APP_PASSWORD"

# Create
LOC=$(curl -si -u "$AUTH" "$BASE/" -X POST \
  -H "Content-Type: application/json" \
  -d '{"summary":"Test ticket","priority":3}' \
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

## 14. Replies Sub-Resource

Each ticket can have one or more **replies** (comments / notes). Replies appear as a child collection at `/tracker/{id}/replies/`.

### Reply JSON Object

```json
{
  "@type":      "Reply",
  "id":         101,
  "message":    "Can you provide more details?",
  "creator":    "admin",
  "created":    "2026-05-26T09:00:00Z",
  "restricted": false
}
```

| Field | Type | Writable | Description |
|-------|------|----------|-------------|
| `@type` | `"Reply"` | No | Always `"Reply"`. |
| `id` | integer | No | Reply ID. Auto-assigned on POST. |
| `message` | string | Yes | **Required on POST/PUT.** The reply text. |
| `creator` | string | No | Auto-set to the authenticated user on creation. |
| `created` | UTC datetime | No | Auto-set on creation. |
| `restricted` | boolean | Yes | `true` = visible only to admins, technicians, and assignees. Default `false`. |

### ACL rules

| Operation | Who can perform it |
|-----------|-------------------|
| GET (read) | Anyone who can read the ticket (same as ticket read ACL) |
| POST (create) | Anyone who can read the ticket (`TRACKER_USER` or higher) |
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
  "101": { "@type": "Reply", "id": 101, "message": "First reply", "creator": "admin", "created": "2026-05-26T09:00:00Z", "restricted": false },
  "102": { "@type": "Reply", "id": 102, "message": "Staff-only note", "creator": "techuser", "created": "2026-05-26T10:00:00Z", "restricted": true }
}
```

### Fetch a single reply

```
GET /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
Accept: application/json
```

Returns the single Reply object or `404` if not found / not visible.

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
{ "message": "Internal: do NOT close yet — waiting for customer confirmation.", "restricted": true }
```

**Response `201 Created`:**

```
Location: /egroupware/groupdav.php/admin/tracker/42/replies/101
```

No body is returned. The new reply ID is in the `Location` header.

### Update a reply (PUT / PATCH)

```
PUT  /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
PATCH /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
Content-Type: application/json
```

PUT replaces the reply fully (message required); PATCH applies only the supplied fields:

```json
{ "message": "Updated reply text." }
```

```json
{ "restricted": true }
```

**Response `204 No Content`** — no body.

### Delete a reply

```
DELETE /egroupware/groupdav.php/{user}/tracker/{id}/replies/{reply_id}
```

**Response `204 No Content`** — reply deleted.

### curl examples — replies

```bash
BASE="https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker"
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

## 15. Attachments and Links

Tickets and replies both support file attachments via EGroupware's **Links and Attachments** facility.  
See [Links-and-attachments.md](Links-and-attachments.md) for the complete reference.

### Ticket-level attachments

Attachments on a ticket are accessed through the links sub-collection:

```
/egroupware/groupdav.php/{user}/tracker/{id}/links/
```

#### List attachments on a ticket

```bash
curl -sk -u "admin:PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/" \
  -H "Accept: application/json" | python3 -m json.tool
```

#### Upload an attachment to a ticket (POST multipart)

```bash
curl -sk -u "admin:PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/" \
  -X POST \
  -F "file=@/path/to/screenshot.png;type=image/png" \
  -i | grep -E "HTTP|Location"
```

#### Upload an attachment as raw bytes (PUT)

```bash
curl -sk -u "admin:PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/screenshot.png" \
  -X PUT \
  -H "Content-Type: image/png" \
  --data-binary "@/path/to/screenshot.png" \
  -w "HTTP %{http_code}\n"
```

#### Delete an attachment from a ticket

```bash
curl -sk -u "admin:PASSWORD" \
  "https://personal.egroupware.org/egroupware/groupdav.php/admin/tracker/42/links/{link_id}" \
  -X DELETE -w "HTTP %{http_code}\n"
```

### Reply-level attachments

Replies store attachments via EGroupware's WebDAV VFS tree (not the `/links/` endpoint).  
The WebDAV path for reply attachments is:

```
/egroupware/webdav.php/apps/tracker/{ticket_id}/comments/{reply_id}/
```

#### Upload a file to a reply via WebDAV

```bash
curl -sk -u "admin:PASSWORD" \
  -X PUT \
  -H "Content-Type: image/png" \
  --data-binary "@/path/to/attachment.png" \
  "https://personal.egroupware.org/egroupware/webdav.php/apps/tracker/42/comments/101/attachment.png" \
  -w "HTTP %{http_code}\n"
```

#### List attachments on a reply

```bash
curl -sk -u "admin:PASSWORD" \
  -X PROPFIND \
  -H "Depth: 1" \
  "https://personal.egroupware.org/egroupware/webdav.php/apps/tracker/42/comments/101/" \
  -w "HTTP %{http_code}\n"
```

#### Download an attachment from a reply

```bash
curl -sk -u "admin:PASSWORD" \
  "https://personal.egroupware.org/egroupware/webdav.php/apps/tracker/42/comments/101/attachment.png" \
  -o attachment.png
```

#### Delete an attachment from a reply

```bash
curl -sk -u "admin:PASSWORD" \
  -X DELETE \
  "https://personal.egroupware.org/egroupware/webdav.php/apps/tracker/42/comments/101/attachment.png" \
  -w "HTTP %{http_code}\n"
```

> **Tip:** EGroupware's WebDAV endpoint (`webdav.php`) supports the full WebDAV protocol (PROPFIND, PUT, GET, DELETE, MKCOL, COPY, MOVE). Clients such as `cadaver`, WinSCP, and macOS Finder can mount the VFS tree directly for drag-and-drop file management on both tickets and replies.
