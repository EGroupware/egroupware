# EGroupware REST API for Mail

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- E-Mail application

> Currently only implemented are:
> * sending mail non-interactive
> * launching interactive compose windows
> * viewing EML files 
> * setting the vacation notice
> * creating or editing mail accounts and identities
> * read-only listing of folders and emails (JMAP-lite, not full JMAP), see
>   [Folders and Emails (JMAP-lite)](#folders-and-emails-jmap-lite) below

> **Mail accounts in EGroupware can be for a single or multiple accounts or groups or even for everyone.**
> A mail account can have multiple identities or signatures. If a mail account is for more than one 
> EGroupware account or a group, they can have personal identities/signatures.

> As far as the REST API is concerned mail accounts with 1 to N identities are always referenced with the 
> `ident_id` of the identity (always belonging to a single mail account!), and not the `acc_id` of a mail account!

Implemented requests (relative to https://example.org/egroupware/groupdav.php)

#### **GET** `/mail` get different mail accounts available to user
<details>
  <summary>Example: Querying available identities / signatures</summary>

```bash
curl -i https://example.org/egroupware/groupdav.php/mail --user <user> -H 'Accept: application/json'
HTTP/1.1 200 OK
Content-Type: application/json

{
        "responses": {
"/ralf/mail/1": "Ralf Becker boulder.egroupware.org <ralf@boulder.egroupware.org>",
"/ralf/mail/52": "Ralf Becker  <sysop@testbox.egroupware.org>",
"/ralf/mail/85": "Ralf Becker  <RalfBeckerKL@gmail.com>"
        }
}
```
</details>

#### **POST** `/mail[/<id>]` send mail for default or given identity <id>
<details>
  <summary>Example: Sending mail</summary>

The content of the POST request is a JSON encoded object with following attributes
- `to`: array of strings with (RFC882) email addresses like `["info@egroupware.org", "Ralf Becker <rb@egroupware.org"]`
- `cc`: array of strings with (RFC882) email addresses (optional)
- `bcc`: array of strings with (RFC882) email addresses (optional)
- `replyto`: string with (RFC822) email address (optional)
- `subject`: string with subject
- `body`: string plain text body (optional)
- `bodyHtml`: string with html body (optional)
- `replyEml`: string returned from uploaded eml file to reply to (optional)
- `attachments`: array of strings returned from uploaded attachments (see below) or VFS path `["/mail/attachments/<token>", "/home/<user>/<filename>", ...]`
- `attachmentType`: one of the following strings (optional, default "attach")
  - "attach" send as attachment
  - "link" send as sharing link
  - "share_ro" send a readonly share using the current file content (VFS only)
  - "share_rw" send as writable share (VFS and EPL only)
- `shareExpiration`: "yyyy-mm-dd" or e.g. "+2days", default not accessed in 100 days (EPL only)
- `sharePassword`: string with password required to access share, default none (EPL only)
- `folder`: folder to store send mail, default Sent folder
- `priority`: 1: high, 3: normal (default), 5: low

```
curl -i https://example.org/egroupware/groupdav.php/mail --user <user> \
  -X POST -H 'Content-Type: application/json' \
  --data-binary '{"to":["info@egroupware.org"],"subject":"Testmail","body":"This is a test :)\n\nRegards"}'
HTTP/1.1 200 Ok
Content-Type: application/json

{
  "status": 200,
  "message": "Mail successful sent"
}
```
If you are not authenticated you will get:
```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Basic realm="EGroupware CalDAV/CardDAV/GroupDAV server"
X-WebDAV-Status: 401 Unauthorized
```
If you use a token to authenticate, SMTP must work without password, or you need an SMTP-only account!
It's probably still not possible to save a successful sent mail to the Sent folder:
```
{
    "status": 200,
    "warning": "Mail NOT saved to Sent folder, as no user password",
    "message": "Mail successful sent"
}
```
If there is an error sending the mail you will get:
```
HTTP/1.1 500 Internal Server Error
Content-Type: application/json

{"error": 500,"message":"SMTP Server not reachable"}
```
</details>

#### **POST** `/mail[/<id>]/compose` launch compose window
<details>
  <summary>Example: Opening a compose window</summary>

Parameters are identical to send mail request above, thought there are additional responses:
- compose window successful opened
```
HTTP/1.1 200 OK
Content-Type: application/json

{
    "status": 200,
    "message": "Request to open compose window sent"
}
```
- user is not online, therefore compose window can NOT be opened
```
404 Not found
Content-Type: application/json

{
    "error": 404,
    "message": "User 'ralf' (#5) is NOT online"
}
```
</details>

#### **POST** `/mail/attachments/<filename>` upload mail attachments
<details>
  <summary>Example: Uploading an attachment  to be used for sending or composing mail</summary>

The content of the POST request is the attachment, a Location header in the response gives you a URL 
to use in further requests, instead of the attachment.
  
```
curl -i https://example.org/egroupware/groupdav.php/mail/attachments/<filename> --user <user> \
    --data-binary @<file> -H 'Content-Type: <content-type-of-file>'
HTTP/1.1 201 Created
Location: https://example.org/egroupware/groupdav.php/mail/attachments/<token>

{
    "status": 201,
    "message": "Attachment stored",
    "location": "/mail/attachments/<token>"
}
```
> When using curl to upload attachments it's important to use `--data-binary`, just `-d` or `--data` is NOT sufficient!

> Use a `X-No-Location: true` header to get NO `Location: <url>` header with HTTP status `201 Created` back, but a simple `200 Ok`!
</details>

#### **POST** `/mail[/<id>]/view` view an eml file
<details>
  <summary>Example: Uploading an eml file to be viewed</summary>

The content of the POST request is the eml-file. 
It gets imported to the Drafts folder of the selected or default mail account, 
and is then viewed from there.

The user has the ability to answer or forward the message, or download attachments.

```
curl -i https://example.org/egroupware/groupdav.php/mail/view --user <user> \
    --data-binary @<eml-file> -H 'Content-Type: message/rfc822'
HTTP/1.1 200 Ok

{
    "status": 200,
    "message": "Request to open view window sent",
}
```
> You get a `404 Not Found`, if the user is NOT online, like in compose.

> When using curl to upload attachments it's important to use `--data-binary`, just `-d` or `--data` is NOT sufficient!
</details>

#### **POST** `/mail[/<id>]/vacation` enable or disable vacation message or forwarding

<details>
  <summary>Example: Setting a vacation message with given start- and end-date</summary>

The content of the POST request is a JSON encoded object with following attributes
- `status`: "on" (default, if not start/end), "off" or "by_date" (default, if start/end given)
- `start`: start-date "YYYY-mm-dd", or e.g. "+2days" (optional)
- `end`: end-date (last day of vacation) "YYYY-mm-dd" (optional)
- `text`: vacation notice to the sender (can container $$start$$ and $$end$$ placeholders)
- `modus`: "notice+store" (default) send vacation notice and store in INBOX, "notice": only send notice, "store": only store
- `forwards`: array of strings with (RFC882) email addresses (optional, default no forwarding)
- `addresses`: array of strings with (RFC882) email addresses (optional, default primary email address only)
- `days`: integer, after how many days should a sender get the vacation message again (optional, otherwise default is used)

> The `POST` request is handled like a `PATCH`, only the given attributes are replaced, use null to unset them.

```
curl -i https://example.org/egroupware/groupdav.php/mail/vacation --user <user> -X POST -H 'Content-Type: application/json' \
  --data-binary '{"text":"I'm away from $$start$$ to $$end$$, will respond when I'm back.","start":"2023-01-01","end":"2023-01-10"}'
    
HTTP/1.1 200 Ok

{
    "status": 200,
    "message": "Vacation handling stored"
}
```
</details>

#### **GET** `/mail[/<id>]/vacation` get current vacation message/handling

<details>
  <summary>Example: Querying the current vacation handling</summary>

For an explanation of the returned attributes of the returned object, see the POST request.

```
curl -i https://example.org/egroupware/groupdav.php/mail/vacation --user <user> -H 'Accept: application/json'
    
HTTP/1.1 200 Ok

{
  "start":"2023-01-01",
  "end":"2023-01-10",
  "status": "by_date",
  "modus": "notice+store",
  "text":"I'm away from $$start$$ to $$end$$, will respond when I'm back.",
  "days": 5,
  "addresses": ["me@example.org","webmaster@example.org"],
  "forwards": ["hugo.meyer@example.org","sven@example.com"]
}
```
</details>

#### **GET** `/mail/<id>` get mail account

<details>
  <summary>Example: Querying a mail account by its identId (DB column ident_id, NOT acc_id!)</summary>

```
curl -i https://example.org/egroupware/groupdav.php/mail/123 --user <user> -H 'Accept: application/json'
    
HTTP/1.1 200 Ok

{
    "accDomain": "boulder.egroupware.org",
    "accFolderArchive": "",
    "accFolderDraft": "Drafts",
    "accFolderHam": "",
    "accFolderJunk": "Junk",
    "accFolderSent": "Sent",
    "accFolderTemplate": "Templates",
    "accFolderTrash": "Trash",
    "accFurtherIdentities": false,
    "accId": 1,
    "accImapAccountId": "c",
    "accImapAdminAccountId": 0,
    "accImapAdminCredId": 184,
    "accImapAdminPassword": "********",
    "accImapAdminPwEnc": 4,
    "accImapAdminUsername": "dovecot@boulder.egroupware.org",
    "accImapCredId": "email",
    "accImapDefaultQuota": null,
    "accImapHost": "mail",
    "accImapLogintype": "email",
    "accImapPassword": "********",
    "accImapPort": 993,
    "accImapSsl": 2,
    "accImapTimeout": null,
    "accImapType": "EGroupware\\Api\\Mail\\Imap\\Jmap",
    "accImapUsername": "ralf@boulder.egroupware.org",
    "accModified": "2026-07-21 18:57:03",
    "accModifier": 5,
    "accName": "EGroupware Mail",
    "accSieveEnabled": true,
    "accSieveHost": "mail",
    "accSievePort": 4190,
    "accSieveSsl": 1,
    "accSmimeAccountId": 5,
    "accSmimeCredId": 123,
    "accSmimePassword": "********",
    "accSmimePwEnc": 4,
    "accSmimeUsername": "ralf@boulder.egroupware.org",
    "accSmtpAccountId": "c",
    "accSmtpAuthSession": true,
    "accSmtpCredId": "email",
    "accSmtpHost": "mail",
    "accSmtpPassword": "********",
    "accSmtpPort": 465,
    "accSmtpSsl": 2,
    "accSmtpType": "EGroupware\\Api\\Mail\\Smtp\\Stalwart",
    "accSmtpUsername": "ralf@boulder.egroupware.org",
    "accSpamAccountId": 0,
    "accSpamApi": "https://spamtitan.egroupware.org",
    "accSpamCredId": 122,
    "accSpamPassword": "********",
    "accSpamPwEnc": 4,
    "accSpamUsername": "https://spamtitan.egroupware.org",
    "accUserEditable": true,
    "accUserForward": true,
    "accountId": [
        "0"
    ],
    "accountStatus": true,
    "deliveryMode": null,
    "identEmail": "ralf@boulder.egroupware.org",
    "identId": 123,
    "identName": "Test Ralf",
    "identOrg": "boulder.egroupware.org",
    "identRealname": "Herr Ralf Becker",
    "identSignature": "<p style=\"font-family: arial, helvetica, sans-serif; font-size: 10pt;\">{{n_fn}}<br />{{org_name}}<br />{{#Test}}</p>\n<p style=\"font-family: arial, helvetica, sans-serif; font-size: 10pt;\">{{user/n_fn}}<br />{{user/#Test}}</p>",
    "mailAlternateAddress": [
        "ralf.becker@boulder.egroupware.org",
        "buyer.ag@boulder.egroupware.org",
        "bb@boulder.egroupware.org",
        "postmaster@boulder.egroupware.org"
    ],
    "mailForwardingAddress": [],
    "mailLocalAddress": "ralf@boulder.egroupware.org",
    "notifyAccountId": 0,
    "notifyFolders": [],
    "quotaLimit": 200,
    "quotaUsed": 0
}
```
</details>


#### **PATCH** `/mail/<id>` modifying a mail account

<details>
  <summary>Example: Modifying a mail account specified by its identId</summary>

```
curl -i https://example.org/egroupware/groupdav.php/mail/123 --user <user> -H 'Content-Type: application/json' -X PATCH \
  -d '{"identName": "Test Ralf", "quotaLimit": 200, "accountStatus": true}'

HTTP/2 204
x-dav-powered-by: EGroupware 26.1 CalDAV/CardDAV/GroupDAV server
x-webdav-status: 204 No Content
```
</details>


## Folders and Emails (JMAP-lite)

> **Status: implemented (2026-09-09).** `GET /mail/folders` and `GET /mail/folders/<folderId>/emails`
> are live-verified against a running instance; the single-folder/single-email/attachment-download
> endpoints are implemented but not yet live-verified. See `doc/ai/projects/mail-rest-jmap-lite.md`
> for the full plan, architecture, and backend-parity notes.

These endpoints let a client read a mail account's folders and the emails inside them, using JMAP's own
`Mailbox`/`Email` data model (RFC 8620/8621) - but this is **not** a full JMAP implementation. There is
no `/jmap` session/capabilities resource, no method batching, no `state`/`changes`, no push, and no
write methods (`Email/set`, `Mailbox/set`, ...). Just a handful of plain `GET` endpoints.

**These endpoints proxy the account's real JMAP session, they don't reshape it.** Every mail account
already has a uniform JMAP session internally - genuine JMAP-over-HTTP for Stalwart-backed accounts, or
a local server-side JMAP-shaped emulation ("JmapShim") for every plain-IMAP account (Dovecot, Cyrus,
even OAuth-authenticated external accounts). This API calls that session's `Mailbox/Email` `get`/`query`
directly and returns what it returns - a `properties` query parameter is forwarded verbatim to the
underlying JMAP call, exactly like real JMAP's own `properties` argument, rather than this API
maintaining its own fixed field allow-list. Consequences worth knowing:
- **`id` values are genuinely the session's own ids** - real opaque JMAP ids for a Stalwart-backed
  account, or the shim's own stable `base64(folder path)` (folders) / IMAP `UID` (emails) ids for a
  plain-IMAP account. Either way: **treat every `id`/`parentId`/`mailboxIds` key/`blobId` as fully
  opaque** - never construct, parse, or reuse one outside a request to this same account through this
  same API.
- **A `<folderId>` path segment also accepts a human-readable literal folder name/path** instead of
  the opaque id - a single top-level folder can be named directly (eg. `INBOX`, `Sent`); a deeper one
  needs its segments joined with `"::"` (eg. `INBOX::Archive::2026` for `INBOX/Archive/2026`) since a
  literal `"/"` can't appear inside one URL path segment. This is purely an additional, optional way to
  name the same resource - the real `id` always keeps working unchanged, and every response's `id`
  field is still the opaque id, never this shorthand. `INBOX` is matched case-insensitively, but **only
  as the very first path segment** (RFC 3501 §5.1 - IMAP's own top-level-mailbox special case); any
  other segment is an ordinary, case-sensitive folder name. Every `Mailbox` object also carries a `path`
  field (the same `"/"`-joined canonical form, always present regardless of the `properties` query
  parameter) so a client can see the human-readable name it could use next time, without needing to
  already know the folder's own id - and the folders-list response envelope's own resource-path keys
  use this same notation too (eg. `"/mail/folders/INBOX::Sent"`), for consistency.
- **The two backends are not always field-identical**, because this API doesn't force them to be:
  - A Stalwart-backed `Mailbox` includes `myRights`, `totalThreads`, `unreadThreads`. A plain-IMAP
    (shim-backed) `Mailbox` currently does **not** include those three, but does include two
    shim-only extras with no JMAP counterpart: `hasChildren`, `aclCapable`.
  - `Email.threadId` is available on **both** backends when explicitly requested via `properties`
    (not included by default).
  - A single email's `bodyStructure`/`textBody`/`htmlBody`/`bodyValues` (genuine JMAP body shape, not a
    flattened simplification) are fully supported on both backends already.

#### **GET** `/mail[/<id>]/folders` list folders (mailboxes)

<details>
  <summary>Example: Listing folders for the default identity</summary>

Query parameters (all optional):
- `properties`: comma-separated list of `Mailbox` properties to return (forwarded to the underlying
  `Mailbox/get` call) - default is all standard properties for that backend (see backend-parity notes
  above)
- `subscribedOnly`: `true` (default) or `false` - only list subscribed folders, or all folders

```
curl -i https://example.org/egroupware/groupdav.php/mail/folders --user <user> -H 'Accept: application/json'

HTTP/1.1 200 Ok
Content-Type: application/json

{
  "responses": {
    "/mail/folders/INBOX": {
      "id": "<folderId>",
      "name": "INBOX",
      "path": "INBOX",
      "parentId": null,
      "role": "inbox",
      "totalEmails": 42,
      "unreadEmails": 3,
      "isSubscribed": true,
      "isSelectable": true
    },
    "/mail/folders/INBOX::Sent": {
      "id": "<folderId2>",
      "name": "Sent",
      "path": "INBOX/Sent",
      "parentId": "<folderId>",
      "role": "sent",
      "totalEmails": 137,
      "unreadEmails": 0,
      "isSubscribed": true,
      "isSelectable": true
    }
  }
}
```

> The response-envelope's own resource-path keys (`"/mail/folders/INBOX::Sent"`) use the same
> human-readable `"::"`-path notation as the `path` field, not the opaque `id` - each object's own
> `id`/`parentId` fields are still the real, opaque ids, unaffected.

`isSelectable` is `false` only for the shim's synthetic IMAP shared/other-users namespace-root
pseudo-folder (`user`/`shared`) - a real listing entry (so a client can navigate into it), but never a
real, fetchable mailbox itself; fetching its emails returns a `400`. Always `true` on a Stalwart/real-JMAP
account, which has no namespace-root concept at all.
</details>

#### **GET** `/mail[/<id>]/folders/<folderId>` get a single folder

`<folderId>` also accepts the `"::"`-joined literal path syntax (eg. `INBOX::Sent`) - see the notes
above.

<details>
  <summary>Example: Querying a single folder by its id</summary>

```
curl -i https://example.org/egroupware/groupdav.php/mail/folders/<folderId> --user <user> -H 'Accept: application/json'

HTTP/1.1 200 Ok
Content-Type: application/json

{
  "id": "<folderId>",
  "name": "INBOX",
  "path": "INBOX",
  "parentId": null,
  "role": "inbox",
  "totalEmails": 42,
  "unreadEmails": 3,
  "isSubscribed": true,
  "isSelectable": true
}
```

Equivalently: `curl -i https://example.org/egroupware/groupdav.php/mail/folders/INBOX::Sent ...`
</details>

#### **GET** `/mail[/<id>]/folders/<folderId>/emails` list emails in a folder

`<folderId>` also accepts the `"::"`-joined literal path syntax (eg. `INBOX::Sent`) - see the notes
above.

<details>
  <summary>Example: Listing the newest emails in a folder</summary>

Query parameters (all optional):
- `properties`: comma-separated list of `Email` properties to return (forwarded to `Email/get`) -
  default `id,mailboxIds,keywords,size,receivedAt,sentAt,subject,from,to,cc,bcc,hasAttachment,preview`
- `position`: integer, default `0` - offset for paging
- `limit`: integer, default `50`, max `200` - max emails to return
- `sort`: e.g. `receivedAt desc` (default) or `receivedAt asc`
- `filter[before]` / `filter[after]`: ISO 8601 date-time - received-date range
- `filter[hasAttachment]`: `true`/`false`
- `filter[text]`: string - basic subject/from/body search
- `filter[keyword]` / `filter[notKeyword]`: e.g. `$seen`, `$flagged` - flag filter

```
curl -i https://example.org/egroupware/groupdav.php/mail/folders/<folderId>/emails?limit=2 --user <user> -H 'Accept: application/json'

HTTP/1.1 200 Ok
Content-Type: application/json

{
  "responses": {
    "/mail/folders/<folderId>/emails/<emailId>": {
      "id": "<emailId>",
      "mailboxIds": {"<folderId>": true},
      "keywords": {"$seen": true},
      "size": 4821,
      "receivedAt": "2026-09-09T08:12:00Z",
      "sentAt": "2026-09-09T08:11:52Z",
      "subject": "Re: Contract Installation",
      "from": [{"name": "Jane Doe", "email": "jane@example.org"}],
      "to": [{"name": "Ralf Becker", "email": "ralf@example.org"}],
      "cc": [],
      "bcc": [],
      "hasAttachment": false
    }
  },
  "position": 0,
  "total": 42
}
```
</details>

#### **GET** `/mail[/<id>]/folders/<folderId>/emails/<emailId>` get a single email

`<folderId>` also accepts the `"::"`-joined literal path syntax (eg. `INBOX::Sent`) - see the notes
above.

<details>
  <summary>Example: Retrieving a single email with its body</summary>

Query parameters:
- `properties`: comma-separated list of `Email` properties - default adds `bodyStructure`, `textBody`,
  `htmlBody`, `attachments`, `bodyValues`, `blobId` (with `fetchAllBodyValues`) to the list-view
  default above

```
curl -i https://example.org/egroupware/groupdav.php/mail/folders/<folderId>/emails/<emailId> --user <user> -H 'Accept: application/json'

HTTP/1.1 200 Ok
Content-Type: application/json

{
  "id": "<emailId>",
  "mailboxIds": {"<folderId>": true},
  "keywords": {"$seen": true},
  "size": 4821,
  "receivedAt": "2026-09-09T08:12:00Z",
  "sentAt": "2026-09-09T08:11:52Z",
  "subject": "Re: Contract Installation",
  "from": [{"name": "Jane Doe", "email": "jane@example.org"}],
  "to": [{"name": "Ralf Becker", "email": "ralf@example.org"}],
  "cc": [],
  "bcc": [],
  "hasAttachment": true,
  "blobId": "<messageBlobId>",
  "bodyStructure": {"partId": "1", "type": "multipart/mixed", "subParts": ["..."]},
  "textBody": [{"partId": "2", "type": "text/plain"}],
  "htmlBody": [{"partId": "3", "type": "text/html"}],
  "bodyValues": {"2": {"value": "Hi Ralf,\n\n...", "isTruncated": false}, "3": {"value": "<p>Hi Ralf,</p>...", "isTruncated": false}},
  "attachments": [
    {"partId": "4", "blobId": "<blobId>", "name": "contract.pdf", "type": "application/pdf", "size": 102400, "cid": null}
  ]
}
```
> This is genuine JMAP body shape (`bodyStructure`/`bodyValues` keyed by `partId`), not a flattened
> plain-string simplification - proxied as-is from the account's JMAP session. The top-level `blobId`
> (RFC 8621 §4.1.1) is the whole raw message - see below for how to download it as `.eml`.
</details>

#### **GET** `/mail[/<id>]/folders/<folderId>/emails/<emailId>/attachments/<blobId>` download attachment content, or the raw `.eml` message

`<folderId>` also accepts the `"::"`-joined literal path syntax (eg. `INBOX::Sent`) - see the notes
above.

<details>
  <summary>Example: Downloading an attachment found in an email's `attachments[]` list</summary>

```
curl -i https://example.org/egroupware/groupdav.php/mail/folders/<folderId>/emails/<emailId>/attachments/<blobId> --user <user>

HTTP/1.1 200 Ok
Content-Type: application/pdf
Content-Disposition: attachment; filename="contract.pdf"

<binary content>
```
> An inline image referenced via `cid:` in `htmlBody` is resolved the same way a real JMAP client would:
> match the `cid` against an entry in `attachments[]`, then download it here by its `blobId`. This API
> does not rewrite `cid:` references itself.
</details>

<details>
  <summary>Example: Downloading the raw <code>.eml</code> message (its own top-level <code>blobId</code>, not one from <code>attachments[]</code>)</summary>

There is no separate "download raw message" endpoint - an email's own top-level `blobId` (RFC 8621
§4.1.1, request it via `?properties=...,blobId` on the single-email `GET` above) is just another blob,
downloaded through this exact same endpoint. Recognized specially for proper headers.

```
curl -i https://example.org/egroupware/groupdav.php/mail/folders/<folderId>/emails/<emailId>/attachments/<messageBlobId> --user <user>

HTTP/1.1 200 Ok
Content-Type: message/rfc822
Content-Disposition: attachment; filename="Re: Contract Installation.eml"

<raw RFC 5322 message>
```
</details>
