# EGroupware REST API for InfoLog

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- InfoLog application

Following RFCs / drafts used/planned for JSON encoding of InfoLog entries
* [rfc8984: JSCalendar: A JSON Representation of Calendar Data](https://datatracker.ietf.org/doc/html/rfc8984)
* [links sub-collection to add attachments and links to other application-entries](Links-and-attachments.md)

### Used JSCalendar Task schema to encode InfoLog entries plus EGroupware&InfoLog specific extensions:
* stock JSCalendar Task attributes like: `uid`, `title`, `start`, ... (see above link)
* `@type` is always `Task` independent of InfoLog type, see `egroupware.org:type` below
* `progress` stock values: 
  * `needs-action` (InfoLog `not-started`)
  * `in-progress` (InfoLog `ongoing`)
  * `completed` (InfoLog `done`)
  * `cancelled`
  * `egroupware.org:<infolog-status>` (all other status supported by InfoLog use a `egroupware.org:` prefix)
* `percentComplete` integer value between 0 and 100
* `participants` contains task-owner (role `owner`), responsible users (role `attendee`) and CC'ed (role `informational`)
  * participants is an object with either the numerical user-id or the email address as attribute-name and an object with the following attributes:
  * `name` of owner, responsible or CC'ed
  * `email` email-address
  * `kind` always `individual` or `group` for groups (other kinds are NOT supported)
  * `roles`: are NOT explicitly stored
    * `attendee` responsible user or group of the task
    * `infomational` CC'ed email addresses to keep informed / send notifications to
    * `owner` this is the task owner, which might or might not be also an `attendee`
  * other attributes like `participationStatus` are currently NOT persisted by InfoLog
* `egroupware.org:type` InfoLog type like `task`, `phone`, `note`, `email`, also custom InfoLog-types admin has created
* `egroupware.org:completed` completed date&time
* `egroupware.org:price` float value with price, if set
* `egroupware.org:pricelist` integer ID of used price-list (readonly)
* `egroupware.org:customfields` custom-field object, if custom-fields are defined by admin and used in the entry
* `relatedTo` object with attribute-names and Relation-object as value with attributes
  * `@type` `Relation`
  * `relation` and value 
    * `parent` attribute-name is UID of the parent-entry
    * `egroupware.org-primary` with the following attribute-names
      * `"<app>:<id>"` or
      * `"addressbook:<value>:<field>"` with addressbook field like `id` or `email` (no `contact_` prefix), or `egroupware.org:customfields/<name>`
  * you can use `null` instead of a Relation-object to delete the existing value (not sending the attribute will NOT delete it!)
  * if your client only allows to send a 1-dimensional object you can use this alternative syntax, to specify the contact to link to via a (unique!) custom-field value
```php
"relatedTo/egroupware.org-primary:addressbook[:<field>]":"<value>"
```
* you can use the [`links` sub-collection](Links-and-attachments.md) of each entry to add relations/links to other application-entries
* InfoLogs primary link can also be created via the [`links` sub-collection](Links-and-attachments.md) with a`rel` of `egroupware.org-primary`

### Supported request methods and examples

#### **GET** to collections with an `Accept: application/json` header return all resources (similar to WebDAV PROPFIND)
<details>
  <summary>Example: Getting all entries of a given users infolog collection</summary>

```
curl https://example.org/egroupware/groupdav.php/<username>/infolog/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/<username>/infolog/1085": {
      "@type": "Task",
      "prodId": "EGroupware InfoLog 23.1.006",
      "uid": "infolog-1085-8623c4830472a8ede9f9f8b30d435ea4",
      "created": "2020-08-08T13:37:46Z",
      "title": "Re: Test creat(ed|or)",
      "start": "2020-08-08T00:00:00",
      "showWithoutTime": true,
      "timeZone": "Europe/Berlin",
      "description": "kkk",
      "participants": {
          "5": {
              "@type": "Participant",
              "name": "Ralf Becker",
              "email": "ralf@example.org",
              "kind": "individual",
              "roles": { "owner": true }
          }
      },
      "status": "confirmed",
      "progress": "in-progress",
      "priority": 9,
      "privacy": "public",
      "percentComplete": 10,
      "egroupware.org:type": "task",
      "relatedTo": {
          "56f7094e-e962-904d-b74a-cf139f9eecb0": {
              "@type": "Relation",
              "relation": "parent"
          }
      }
    },
    "/<username>/infolog/1081": {
      "@type": "Task",
      "prodId": "EGroupware InfoLog 23.1.006",
      "uid": "infolog-1081-8623c4830472a8ede9f9f8b30d435ea4",
      "sequence": "2",
      "created": "2020-08-08T13:07:18Z",
      "title": "Testtitle",
      "start": "2020-08-08T00:00:00",
      "showWithoutTime": true,
      "timeZone": "Europe/Berlin",
      "description": "This is a Test ...",
      "participants": {
          "44": {
              "@type": "Participant",
              "name": "Birgit Becker",
              "email": "birgit@example.org",
              "kind": "individual",
              "roles": { "owner": true }
          }
      },
      "status": "tentative",
      "progress": "egroupware.org:offer",
      "priority": 9,
      "privacy": "public",
      "percentComplete": 10,
      "egroupware.org:type": "task",
    }
  }
}
```
</details>

The following GET parameters are supported to customize the returned properties:
- props[]=<DAV-prop-name> eg. props[]=getetag to return only the ETAG (multiple DAV properties can be specified)
  Default for calendar collections is to only return calendar-data (JsEvent), other collections return all props.
- sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT
- nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
  this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk

The GET parameter `filters` allows to filter or search for a pattern in InfoLog entries:
- `filters[search]=<pattern>` searches for `<pattern>` in the whole contact like the search in the GUI
- `filters[search][%23<custom-field-name>]=<custom-field-value>` filters by a custom-field value
- `filters[<database-column>]=<value>` filters by a DB-column name and value
- `filters[linked]=<app-name>:<numeric-ID>` returns all entries linked to application `<app-name>` with ID `<nummeric-ID>` e.g. "addressbook:123"
> Please note: filters use the database column-names, not JSTask property-names!

Examples: see addressbook


#### **GET**  requests with an `Accept: application/json` header can be used to retrieve single resources / JsContact or JsCalendar schema
<details>
   <summary>Example: GET request for a single resource</summary>

```
curl 'https://example.org/egroupware/groupdav.php/infolog/956' -H "Accept: application/pretty+json" --user <username>
{
    "@type": "Task",
    "prodId": "EGroupware InfoLog 23.1.006",
    "uid": "infolog-956-8623c4830472a8ede9f9f8b30d435ea4",
    "created": "2018-01-31T08:17:07Z",
    "title": "Test notification",
    "start": "2018-01-31T00:00:00",
    "showWithoutTime": true,
    "timeZone": "Europe/Berlin",
    "description": "Blah sdfasdfa",
    "participants": {
        "5": {
            "@type": "Participant",
            "name": "Ralf Becker",
            "email": "ralf@example.org",
            "kind": "individual",
            "roles": { "owner": true }
        },
        "181": {
            "@type": "Participant",
            "name": "Hadi Nategh",
            "email": "hn@example.org",
            "kind": "individual",
            "roles": { "attendee": true }
        },
        "44": {
            "@type": "Participant",
            "name": "Birgit Becker",
            "email": "birgit@example.org",
            "kind": "individual",
            "roles": { "attendee": true }
        }
    },
    "status": "confirmed",
    "progress": "needs-action",
    "priority": 9,
    "privacy": "public",
    "egroupware.org:type": "task",
    "egroupware.org:customfields": {
        "contact": {
            "value": [
                "Internet"
            ],
            "type": "select",
            "label": "Kontakt",
            "values": {
                "Internet": "Internet",
                "Presse": "Presse",
                "Zeitschrift": "Zeitschrift",
                "Empfehlung": "Empfehlung",
                "Hotel": "Hotel",
                "Unknown": "Weiß nicht",
                "With Space": "With Space"
            }
        },
        "selection": {
            "value": [
                "1"
            ],
            "type": "select",
            "label": "Auswahl",
            "values": {
                "1": "Hugo",
                "2": "Ralf",
                "3": "sonstwer"
            }
        }
    }
}
 ```
</details>

#### **POST** requests to collection with a `Content-Type: application/json` header add new entries in infolog collections
  (Location header in response gives URL of new resource)
<details>
   <summary>Example: POST request to create a new resource and use "Prefer: return=representation" to get it fully expanded back</summary>

```
RalfsMac:mserver ralf$ cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/infolog/' \
  -H "Content-Type: application/json" -H "Prefer: return=representation" -H "Accept: application/pretty+json" \
  -X POST -d @- --user <username>
{                      
  "title": "Test-Test for Birgit",
  "start": "2024-05-10T10:00:00",
  "timeZone": "Europe/Berlin",
  "duration": "PT1H", 
  "due": "2024-06-01T00:00:00",
  "description": "Important task, but quite short ;)",
  "priority": 9,
  "participants": [
    {
      "name": "Birgit Becker",
      "email": "birgit@example.org",
      "roles": { "attendee": true }
    }
  ]
}
EOF

HTTP/1.1 201 Created
Location: /egroupware/groupdav.php/<username>/infolog/1192
ETag: "1192:0:1715012714"
X-WebDAV-Status: 201 Created

{
    "@type": "Task",
    "prodId": "EGroupware InfoLog 23.1.006",
    "uid": "urn:uuid:3933d565-187f-4bad-a44e-82588ef64c88",
    "created": "2024-05-06T14:25:14Z",
    "title": "Test-Test for Birgit",
    "start": "2024-05-10T10:00:00",
    "timeZone": "Europe/Berlin",
    "due": "2024-06-01T00:00:00",
    "duration": "PT1H",
    "description": "Important task, but quite short ;)",
    "participants": {
        "5": {
            "@type": "Participant",
            "name": "Ralf Becker",
            "email": "ralf@example.org",
            "kind": "individual",
            "roles": { "owner": true }
        },
        "44": {
            "@type": "Participant",
            "name": "Birgit Becker",
            "email": "birgit@example.org",
            "kind": "individual",
            "roles": { "attendee": true }
        }
    },
    "status": "confirmed",
    "progress": "completed",
    "privacy": "public",
    "egroupware.org:type": "task"
}
```
</details>

#### **PUT**  requests with  a `Content-Type: application/json` header allow modifying single resources (requires to specify all attributes!)

<details>
   <summary>Example: PUT request with UID to update an existing resource or create it, if not exists</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/infolog/1192' -X PUT -d @- -H "Content-Type: application/json" --user <username>
{
    "@type": "Task",
    "prodId": "EGroupware InfoLog 23.1.006",
    "uid": "urn:uuid:3933d565-187f-4bad-a44e-82588ef64c88",
    "created": "2024-05-06T14:25:14Z",
    "title": "Test-Test for Birgit updated",
....
}
EOF
```
Update of an existing one:
```
HTTP/1.1 204 No Content
```
New tast:
```
HTTP/1.1 201 Created
Location: https://example.org/egroupware/groupdav.php/<username>/infolog/1234
```
</details>


#### **PATCH** request with a `Content-Type: application/json` header allow to modify a single resource by only specifying changed attributes as a [PatchObject](https://www.rfc-editor.org/rfc/rfc8984.html#type-PatchObject)

<details>
   <summary>Example: PATCH request to modify an event with partial data</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/infolog/1234' -X PATCH -d @- -H "Content-Type: application/json" --user <username>
{
  "title": "New title"
}
EOF

HTTP/1.1 204 No content
```
</details>

#### **DELETE** requests delete single resources
<details>
   <summary>Example: Delete an existing event</summary>

> Please note: the "Accept: application/json" header is required, as the CalDAV server would return 404 NotFound as the url does NOT end with .ics

```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/infolog/1234' -X DELETE -H "Accept: application/json" --user <username>

HTTP/1.1 204 No Content
```
</details>

* one can use `Accept: application/pretty+json` to receive pretty-printed JSON e.g. for debugging and exploring the API