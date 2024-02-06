# EGroupware REST API for Links and attachments
* linking application entries to other application entries
* attaching files to application entries
* listing, creating and deleting links and attachments

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- application the link or attachment is created for

### Following schema is used for JSON encoding of links and attachments

* @type: `Link`
* href: string URI to linked entry or attachments
* title: string title of link
* contentType: string `application/json` for links, content-type of attachments
* size: size of attachments
* egroupware.org-remark: string
* egroupware.org-app: string application name of the linked entry
* egroupware.org-id: string application ID of the linked entry
* rel: string `egroupware.org-primary` to mark a primary link for InfoLog entries

### Supported request methods and examples

* **GET** to application entry collections to return all links and attachments
<details>
  <summary>Example: Getting all links and attachments of a given application entry</summary>
  
```
curl https://example.org/egroupware/groupdav.php/<username>/<app>/<id>/links/ -H "Accept: application/pretty+json" --user <username>
HTTP/1.1 200 Ok
Content-Type: application/json

{
    "responses": {
        "/<username>/<app>/<id>/links/<link-id>": {
            "@type": "Link",
            "href": "https://example.org/egroupware/groupdav.php/ralf/addressbook/46",
            "contentType": "application/json",
            "title": "EGroupware GmbH: Becker, Ralf",
            "egroupware.org-app": "addressbook",
            "egroupware.org-id": "46",
            "egroupware.org-remark": "Testing ;)"
        },
        "/<username>/<app>/<id>/links/<link-id>": {
            "@type": "Link",
            "href": "https://example.org/egroupware/groupdav.php/ralf/infolog/1161",
            "contentType": "application/json",
            "title": "Test mit primärem Link (#1161)",
            "egroupware.org-app": "infolog",
            "egroupware.org-id": "1161"
        },
        "/<username>/<app>/<id>/links/<attachment-id>": {
            "@type": "Link",
            "href": "https://example.org/egroupware/webdav.php/apps/timesheet/199/image.svg",
            "contentType": "image/svg+xml",
            "size": 17167,
            "title": "image.svg"
        }
    }
}
```
</details>

* **POST** request to upload an attachment or link with another application entry

<details>
   <summary>Example: Adding a PDF as attachment to an application entry</summary>
   
```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/<app>/<id>/links/<filename>' -H "Content-Type: application/pdf" --data-binary @<path-to-pdf> --user <username>

HTTP/1.1 204 Created
Location: https://example.org/egroupware/groupdav.php/<username>/<app>/<id>/links/<attachment-id>
```
</details>

<details>
   <summary>Example: Creating a link from one application entry to another</summary>

```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/<app>/<id>/links/' -H "Content-Type: application/json" --data-binary @- --user <username> <<<EOF
{"app":"<2nd-app>","id":<2nd-app-id>,"remark":"This is a test ;)"}
EOF

HTTP/1.1 204 Created
Location: https://example.org/egroupware/groupdav.php/<username>/<app>/<id>/links/<link-id>
```
</details>

<details>
   <summary>Example: Creating the primary link for an InfoLog entry</summary>

```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/infolog/<id>/links/' -H "Content-Type: application/json" --data-binary @- --user <username> <<<EOF
{"app":"<2nd-app>","id":<2nd-app-id>,"rel":"egroupware.org-primary"}
EOF

HTTP/1.1 204 Created
Location: https://example.org/egroupware/groupdav.php/<username>/infolog/<id>/links/<link-id>
```
</details>

> `<id>` is the numerical ID of the entry of application `<app>`, NOT the UUID some applications have!
> `<2nd-app-id>` is also the numerical ID of `<2nd-app>`, not the UUID

* **DELETE** request to remove a link or attachment

<details>
    <summary>Example: deleting an attachment or link</summary>

```
curl -X DELETE 'https://example.org/egroupware/groupdav.php/<app>/<id>/links/<link-or-attachment-id>' --user <username>

HTTP/1.1 201 No Content
```
</details>

> one can use ```Accept: application/pretty+json``` to receive pretty-printed JSON eg. for debugging and exploring the API