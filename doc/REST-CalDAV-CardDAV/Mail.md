# EGroupware REST API for Mail

> Currently only sending mail or launching interactive compose windows

Implemented requests (relative to https://example.org/egroupware/groupdav.php)

- ```GET /mail``` get different mail accounts available to user
<details>
  <summary>Example: Querying available identities / signatures</summary>

```bash
curl -i https://example.org/egroupware/mail --user <user> -H 'Accept: application/json'
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8

{
        "responses": {
"/ralf/mail/1": "Ralf Becker boulder.egroupware.org <ralf@boulder.egroupware.org>",
"/ralf/mail/52": "Ralf Becker  <sysop@testbox.egroupware.org>",
"/ralf/mail/85": "Ralf Becker  <RalfBeckerKL@gmail.com>"
        }
}
```
</details>

- ```POST /mail[/<id>]``` send mail for default or given account <id>
<details>
  <summary>Example: Sending mail</summary>

The content of the POST request is a JSON encoded object with following attributes
- ```to```: array of strings with (RFC882) email addresses like ```["info@egroupware.org", "Ralf Becker <rb@egroupware.org"]```
- ```cc```: array of strings with (RFC882) email addresses (optional)
- ```bcc```: array of strings with (RFC882) email addresses (optional)
- ```replyTo```: string with (RFC822) email address (optional)
- ```subject```: string with subject
- ```body```: string plain text body (optional)
- ```bodyHtml```: string with html body (optional)
- ```attachments```: array of strings returned from uploaded attachments (see below) or VFS path ```["/mail/attachment/<token>", "/home/<user>/<filename>", ...]```
- ```attachmentType```: one of the following strings (optional, default "attach")
  - "attach" send as attachment
  - "link" send as sharing link
  - "share_ro" send a readonly share using the current file content (VFS only)
  - "share_rw" send as writable share (VFS and EPL only)
- ```shareExpiration```: "yyyy-mm-dd", default not accessed in 100 days (EPL only)
- ```sharePassword```: string with password required to access share, default none (EPL only)
- ```folder```: folder to store send mail, default Sent folder

```bash
curl -i https://example.org/egroupware/mail --user <user> \
  -X POST -H 'Content-Type: application/json' \
  --content `{"to":["info@egroupware.org"],"subject":"Testmail","body":"This is a test :)\n\nRegards"}`
HTTP/1.1 204 No Content
```
If you are not authenticated you will get:
```
HTTP/1.1 401 Unauthorized
WWW-Authenticate: Basic realm="EGroupware CalDAV/CardDAV/GroupDAV server"
X-WebDAV-Status: 401 Unauthorized
```
If there is an error sending the mail you will get:
```
HTTP/1.1 500 Internal Server Error
Content-Type: application/json
Content-Length: ...

{"error": 123,"message":"SMTP Server not reachable"}
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
    "message": "Request to open compose window sent",
    "extra": {
        "preset": {
            "to": [
                "Birgit Becker <bb@egroupware.org"
            ],
            "cc": [
                "info@egroupware.org"
            ],
            "subject": "Testmail",
            "body": "<pre>This is a test :)\n\nRegards</pre>",
            "mimeType": "html",
            "identity": "52"
        }
    }
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
curl -i https://example.org/egroupware/mail/attachment/<filename> --user <user> \
    --data-binary @<file> -H 'Content-Type: <content-type-of-file>'
HTTP/1.1 204 No Content
Location: https://example.org/egroupware/mail/attachment/<token>
```
</details>