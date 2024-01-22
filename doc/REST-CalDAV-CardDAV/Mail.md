# EGroupware REST API for Mail

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- E-Mail application

> Currently only implemented is sending mail, launching interactive compose windows, 
> viewing EML files or setting the vacation notice.

Implemented requests (relative to https://example.org/egroupware/groupdav.php)

- ```GET /mail``` get different mail accounts available to user
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

- ```POST /mail[/<id>]``` send mail for default or given identity <id>
<details>
  <summary>Example: Sending mail</summary>

The content of the POST request is a JSON encoded object with following attributes
- ```to```: array of strings with (RFC882) email addresses like ```["info@egroupware.org", "Ralf Becker <rb@egroupware.org"]```
- ```cc```: array of strings with (RFC882) email addresses (optional)
- ```bcc```: array of strings with (RFC882) email addresses (optional)
- ```replyto```: string with (RFC822) email address (optional)
- ```subject```: string with subject
- ```body```: string plain text body (optional)
- ```bodyHtml```: string with html body (optional)
- ```replyEml```: string returned from uploaded eml file to reply to (optional)
- ```attachments```: array of strings returned from uploaded attachments (see below) or VFS path ```["/mail/attachments/<token>", "/home/<user>/<filename>", ...]```
- ```attachmentType```: one of the following strings (optional, default "attach")
  - "attach" send as attachment
  - "link" send as sharing link
  - "share_ro" send a readonly share using the current file content (VFS only)
  - "share_rw" send as writable share (VFS and EPL only)
- ```shareExpiration```: "yyyy-mm-dd" or e.g. "+2days", default not accessed in 100 days (EPL only)
- ```sharePassword```: string with password required to access share, default none (EPL only)
- ```folder```: folder to store send mail, default Sent folder
- ```priority```: 1: high, 3: normal (default), 5: low

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

- ```POST /mail[/<id>]/compose``` launch compose window
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

- ```POST /mail/attachments/<filename>``` upload mail attachments
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
> When using curl to upload attachments it's important to use ```--data-binary```, just ```-d``` or ```--data``` is NOT sufficient!

> Use a `X-No-Location: true` header to get NO `Location: <url>` header with HTTP status `201 Created` back, but a simple `200 Ok`!
</details>

- ```POST /mail[/<id>]/view``` view an eml file
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

> When using curl to upload attachments it's important to use ```--data-binary```, just ```-d``` or ```--data``` is NOT sufficient!
</details>

- ```POST /mail[/<id>]/vacation``` enable or disable vacation message or forwarding

<details>
  <summary>Example: Setting a vacation message with given start- and end-date</summary>

The content of the POST request is a JSON encoded object with following attributes
- ```status```: "on" (default, if not start/end), "off" or "by_date" (default, if start/end given)
- ```start```: start-date "YYYY-mm-dd", or e.g. "+2days" (optional)
- ```end```: end-date (last day of vacation) "YYYY-mm-dd" (optional)
- ```text```: vacation notice to the sender (can container $$start$$ and $$end$$ placeholders)
- ```modus```: "notice+store" (default) send vacation notice and store in INBOX, "notice": only send notice, "store": only store
- ```forwards```: array of strings with (RFC882) email addresses (optional, default no forwarding)
- ```addresses```: array of strings with (RFC882) email addresses (optional, default primary email address only)
- ```days```: integer, after how many days should a sender get the vacation message again (optional, otherwise default is used)

> The ```POST``` request is handled like a ```PATCH```, only the given attributes are replaced, use null to unset them.

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

- ```GET /mail[/<id>]/vacation``` get current vacation message/handling

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