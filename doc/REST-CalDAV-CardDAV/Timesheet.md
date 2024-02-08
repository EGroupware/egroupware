# EGroupware REST API for Timesheet

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- Timesheet application

Following schema is used for JSON encoding of timesheets
* @type: `timesheet`
* id: integer ID
* title: string
* description: string (multiple lines)
* start: UTCDateTime e.g. `2020-02-03T14:35:37Z`
* duration: integer in minutes
* quantity: double
* project: string
* pm_id: integer ID of ProjectManager app (readonly currently!)
* unitprice: double
* category: category object with a single(!) category-name e.g. `{"category name": true}`
* owner: string with either email or username or integer ID
* created: UTCDateTime e.g. `2020-02-03T14:35:37Z`
* modified: UTCDateTime e.g. `2020-02-03T14:35:37Z`
* modifier: string with either email or username or integer ID
* pricelist: integer ID of projectmanager pricelist item
* status: string
* egroupware.org:customfields: custom-fields object, see other types
* etag: string `"<id>:<modified-timestamp>"` (double quotes are part of the etag!)

### Supported request methods and examples

* **GET** to collections with an ```Accept: application/json``` header return all timesheets (similar to WebDAV PROPFIND)
<details>
  <summary>Example: Getting all timesheets of a given user</summary>
  
```
curl https://example.org/egroupware/groupdav.php/<username>/timesheet/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/<username>/timesheet/1": {
        "@type": "timesheet",
        "id": 1,
        "title": "Test",
        "start": "2005-12-16T23:00:00Z",
        "duration": 150,
        "quantity": 2.5,
        "unitprice": 50,
        "category": { "other": true },
        "owner": "ralf@example.org",
        "created": "2005-12-16T23:00:00Z",
        "modified": "2011-06-08T10:51:20Z",
        "modifier": "ralf@example.org",
        "status": "genehmigt",
        "etag": "1:1307537480"
    },
    "/<username>/timesheet/140": {
        "@type": "timesheet",
        "id": 140,
        "title": "Test Ralf aus PM",
        "start": "2016-08-22T12:12:00Z",
        "duration": 60,
        "quantity": 1,
        "owner": "ralf@example.org",
        "created": "2016-08-22T12:12:00Z",
        "modified": "2016-08-22T13:13:22Z",
        "modifier": "ralf@example.org",
        "egroupware.org:customfields": {
            "auswahl": {
                "value": [
                    "3"
                ],
                "type": "select",
                "label": "Auswählen",
                "values": {
                    "3": "Three",
                    "2": "Two",
                    "1": "One"
                }
            }
        },
        "etag": "140:1471878802"
    },
...
}
```
</details>
       
  Following GET parameters are supported to customize the returned properties:
  - props[]=<DAV-prop-name> eg. props[]=getetag to return only the ETAG (multiple DAV properties can be specified)
    Default for timesheet collections is to only return address-data (JsContact), other collections return all props.
  - sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT
  - nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
    this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk

  The GET parameter `filters` allows to filter or search for a pattern in timesheets of a user:
  - `filters[search]=<pattern>` searches for `<pattern>` in the whole timesheet like the search in the GUI
  - `filters[search][%23<custom-field-name>]=<custom-field-value>` filters by a custom-field value
  - `filters[<attribute-name>]=<value>` filters by a DB-column name and value
 
<details>
   <summary>Example: Getting just ETAGs and displayname of all timesheets of a user</summary>
   
```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/timesheet/?props[]=getetag&props[]=displayname' -H "Accept: application/pretty+json" --user <username>

{
  "responses": {
    "/ralf/timesheet/1": {"displayname":"Test","getetag":"\"1:1307537480\""},
    "/ralf/timesheet/140": {"displayname":"Test Ralf aus PM","getetag":"\"140:1471878802\""},
  }
}
```
</details>

<details>
   <summary>Example: Start using a sync-token to get only changed entries since last sync</summary>
   
#### Initial request with empty sync-token and only requesting 10 entries per chunk:
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/?sync-token=&nresults=10&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/timesheet/2050": "Frau Margot Test-Notifikation",
    "/timesheet/2384": "Test Tester",
    "/timesheet/5462": "Margot Testgedöns",
    "/timesheet/2380": "Frau Test Defaulterin",
    "/timesheet/5474": "Noch ein Neuer",
    "/timesheet/5575": "Mr New Name",
    "/timesheet/5461": "Herr Hugo Kurt Müller Senior",
    "/timesheet/5601": "Steve Jobs",
    "/timesheet/5603": "Ralf Becker",
    "/timesheet/1838": "Test Tester"
  },
  "more-results": true,
  "sync-token": "https://example.org/egroupware/groupdav.php/timesheet/1400867824"
}
```
#### Requesting next chunk:
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/?sync-token=https://example.org/egroupware/groupdav.php/timesheet/1400867824&nresults=10&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/timesheet/1833": "Default Tester",
    "/timesheet/5597": "Neuer Testschnuffi",
    "/timesheet/5593": "Muster Max",
    "/timesheet/5628": "2. Test Contact",
    "/timesheet/5629": "Testen Tester",
    "/timesheet/5630": "Testen Tester",
    "/timesheet/5633": "Testen Tester",
    "/timesheet/5635": "Test4 Tester",
    "/timesheet/5638": "Test Kontakt",
    "/timesheet/5636": "Test Default"
  },
  "more-results": true,
  "sync-token": "https://example.org/egroupware/groupdav.php/timesheet/1427103057"
}
```
</details>

<details>
   <summary>Example: Requesting only changes since last sync</summary>
   
#### ```sync-token``` from last sync need to be specified (note the null for a deleted resource!)
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/?sync-token=https://example.org/egroupware/groupdav.php/timesheet/1400867824' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/timesheet/5597": null,
    "/timesheet/5593": {
      TODO
....
    }
  },
  "sync-token": "https://example.org/egroupware/groupdav.php/timesheet/1427103057"
}
```
</details>

* **GET**  requests with an ```Accept: application/json``` header can be used to retrieve single resources / JsTimesheet schema
<details>
   <summary>Example: GET request for a single resource showcasing available fieldes</summary>
   
```
curl 'https://example.org/egroupware/groupdav.php/timesheet/140' -H "Accept: application/pretty+json" --user <username>
{
    "@type": "timesheet",
    "id": 140,
    "title": "Test Ralf aus PM",
    "start": "2016-08-22T12:12:00Z",
    "duration": 60,
    "quantity": 1,
    "project": "2024-0001: Test Project",
    "pm_id": 123,
    "unitprice": 100.0,
    "pricelist": 123,
    "owner": "ralf@example.org",
    "created": "2016-08-22T12:12:00Z",
    "modified": "2016-08-22T13:13:22Z",
    "modifier": "ralf@example.org",
    "egroupware.org:customfields": {
        "auswahl": {
            "value": [
                "3"
            ],
            "type": "select",
            "label": "Auswählen",
            "values": {
                "3": "Three",
                "2": "Two",
                "1": "One"
            }
        }
    },
    "etag": "140:1471878802"
}
```
</details>

* **POST** requests to collection with a ```Content-Type: application/json``` header add new entries in timesheet collections
       (Location header in response gives URL of new resource)
<details>
   <summary>Example: POST request to create a new resource</summary>
   
```
cat <<EOF | curl -i -X POST 'https://example.org/egroupware/groupdav.php/<username>/timesheet/' -d @- -H "Content-Type: application/json" -H 'Accept: application/pretty+json' -H 'Prefer: return=representation' --user <username>
{
    "@type": "timesheet",
    "title": "5. Test Ralf",
    "start": "2024-02-06T10:00:00Z",
    "duration": 60
}
EOF

HTTP/1.1 201 Created
Content-Type: application/json
Location: /egroupware/groupdav.php/ralf/timesheet/204
ETag: "204:1707233040"

{
    "@type": "timesheet",
    "id": 204,
    "title": "5. Test Ralf",
    "start": "2024-02-06T10:00:00Z",
    "duration": 60,
    "quantity": 1,
    "owner": "ralf@example.org",
    "created": "2024-02-06T14:24:05Z",
    "modified": "2024-02-06T14:24:00Z",
    "modifier": "ralf@example.org",
    "etag": "204:1707233040"
}
```
</details>

* **PUT**  requests with  a ```Content-Type: application/json``` header allow modifying single resources (requires to specify all attributes!)

<details>
   <summary>Example: PUT request to update a resource</summary>

```
cat <<EOF | curl -i -X PUT 'https://example.org/egroupware/groupdav.php/<username>/timesheet/1234' -d @- -H "Content-Type: application/json" --user <username>
{
    "@type": "timesheet",
    "title": "6. Test Ralf",
    "start": "2024-02-06T10:00:00Z",
    "duration": 60,
    "quantity": 1,
    "owner": "ralf@example.org",
    "created": "2024-02-06T14:24:05Z",
    "modified": "2024-02-06T14:24:00Z",
    "modifier": "ralf@example.org",
}
EOF

HTTP/1.1 204 No Content
```

</details>


* **PATCH** request with a ```Content-Type: application/json``` header allow to modify a single resource by only specifying changed attributes as a [PatchObject](https://www.rfc-editor.org/rfc/rfc8984.html#type-PatchObject)

<details>
   <summary>Example: PATCH request to modify a timesheet with partial data</summary>

```
cat <<EOF | curl -i -X PATCH 'https://example.org/egroupware/groupdav.php/<username>/timesheet/1234' -d @- -H "Content-Type: application/json" --user <username>
{
  "status": "invoiced"
}
EOF

HTTP/1.1 204 No content
```
</details>

* **DELETE** requests delete single resources

<details>
   <summary>Example: DELETE request to delete a timesheet</summary>

```
curl -i -X DELETE 'https://example.org/egroupware/groupdav.php/<username>/timesheet/1234' -H "Accept: application/json" --user <username>

HTTP/1.1 204 No content
```
</details>

> one can use ```Accept: application/pretty+json``` to receive pretty-printed JSON eg. for debugging and exploring the API