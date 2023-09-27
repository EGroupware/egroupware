# EGroupware REST API for Calendar

Authentication is via Basic Auth with username and a password, or a token valid for:
- either just the given user or all users
- CalDAV/CardDAV Sync (REST API)
- Calendar application

Following RFCs / drafts used/planned for JSON encoding of resources
* [rfc8984: JSCalendar: A JSON Representation of Calendar Data](https://datatracker.ietf.org/doc/html/rfc8984)

### Supported request methods and examples

* **GET** to collections with an ```Accept: application/json``` header return all resources (similar to WebDAV PROPFIND)
<details>
  <summary>Example: Getting all entries of a given users calendar</summary>

```
curl https://example.org/egroupware/groupdav.php/<username>/calendar/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/<username>/calendar/5695": {
        "@type": "Event",
        "prodId": "EGroupware Calendar 23.1.002",
        "uid": "calendar-5695-34b52fc11cfa7e9acea5732210a53f48",
        "sequence": "1",
        "created": "2023-07-14T06:05:53Z",
        "updated": "2023-07-14T08:00:04Z",
        "title": "Test",
        "start": "2023-07-14T10:00:00",
        "timeZone": "Europe/Berlin",
        "duration": "PT1H",
        "participants": {
            "5": {
                "@type": "Participant",
                "name": "Ralf Becker",
                "email": "ralf@boulder.egroupware.org",
                "kind": "individual",
                "roles": {
                    "owner": true,
                    "chair": true
                },
                "participationStatus": "accepted"
            }
        },
        "status": "confirmed",
        "priority": 5,
        "privacy": "public"
    },
    "/<username>/calendar/5699": {
        "@type": "Event",
        "prodId": "EGroupware Calendar 23.1.002",
        "uid": "calendar-5699-34b52fc11cfa7e9acea5732210a53f48",
        "sequence": "5",
        "created": "2023-07-24T10:06:23Z",
        "updated": "2023-07-24T13:11:49Z",
        "title": "Monday and Wednesday 13h",
        "start": "2023-07-24T13:00:00",
        "timeZone": "Europe/Berlin",
        "duration": "PT1H",
        "recurrenceRules": [
            {
                "@type": "RecurrenceRule",
                "frequency": "weekly",
                "until": "2023-08-30T13:00:00",
                "byDay": {
                    "@type": "NDay",
                    "day": "mo,we"
                }
            }
        ],
        "recurrenceOverrides": {
            "2023-07-31T13:00:00": { "excluded": true },
            "2023-07-26T13:00:00": {
                "sequence": "1",
                "updated": "2023-07-24T11:39:44Z",
                "title": "Monday und Wednesday 13h, but 26th 14h",
                "start": "2023-07-26T14:00:00",
                "description": "sdfasdf",
                "showWithoutTime": null,
                "categories": null,
                "alerts": {
                    "64be4dc0-a044-4b27-b450-0026ac120002": {
                        "@type": "Alert",
                        "trigger": {
                            "@type": "OffsetTrigger",
                            "offset": 0
                        }
                    },
                    "64be4d1f-c958-4fbd-afc3-0026ac120002": null
                }
            }
        },
        "participants": {
            "5": {
                "@type": "Participant",
                "name": "Ralf Becker",
                "email": "ralf@boulder.egroupware.org",
                "kind": "individual",
                "roles": {
                    "owner": true,
                    "chair": true
                },
                "participationStatus": "accepted"
            }
        },
        "alerts": {
            "64be4d1f-c958-4fbd-afc3-0026ac120002": {
                "@type": "Alert",
                "trigger": {
                    "@type": "OffsetTrigger",
                    "offset": 0
                }
            }
        },
        "status": "confirmed",
        "priority": 5,
        "privacy": "public"
    },
    "/<username>/calendar/5701": {
        "@type": "Event",
        "prodId": "EGroupware Calendar 23.1.002",
        "uid": "calendar-5701-34b52fc11cfa7e9acea5732210a53f48",
        "sequence": "1",
        "created": "2023-07-24T12:31:58Z",
        "updated": "2023-07-24T12:41:54Z",
        "title": "Di und Do den ganzen Tag",
        "start": "2023-07-25T00:00:00",
        "timeZone": "Europe/Berlin",
        "showWithoutTime": true,
        "duration": "P1D",
        "recurrenceRules": [
            {
                "@type": "RecurrenceRule",
                "frequency": "weekly",
                "until": "2023-08-03T00:00:00",
                "byDay": {
                    "@type": "NDay",
                    "day": "tu,th"
                }
            }
        ],
        "recurrenceOverrides": {
            "2023-07-27T00:00:00": {
                "title": "Di und Do den ganzen Tag: AUSNAHME",
                "start": "2023-07-27T00:00:00",
                "description": "adsfads",
                "sequence": null,
                "categories": null,
                "participants": {
                    "44": {
                        "@type": "Participant",
                        "name": "Birgit Becker",
                        "email": "birgit@boulder.egroupware.org",
                        "kind": "individual",
                        "roles": { "attendee": true },
                        "participationStatus": "needs-action"
                    }
                },
                "alerts": {
                    "64be7192-d4e4-4609-8c7a-004dac120002": {
                        "@type": "Alert",
                        "trigger": {
                            "@type": "OffsetTrigger",
                            "offset": 0
                        }
                    },
                    "64be6f3e-bc8c-4e78-9f96-004bac120002": null
                }
            }
        },
        "freeBusyStatus": "free",
        "participants": {
            "5": {
                "@type": "Participant",
                "name": "Ralf Becker",
                "email": "ralf@boulder.egroupware.org",
                "kind": "individual",
                "roles": {
                    "owner": true,
                    "chair": true
                },
                "participationStatus": "accepted"
            }
        },
        "alerts": {
            "64be6f3e-bc8c-4e78-9f96-004bac120002": {
                "@type": "Alert",
                "trigger": {
                    "@type": "OffsetTrigger",
                    "offset": 0
                }
            }
        },
        "status": "confirmed",
        "priority": 5,
        "privacy": "public"
    }
  }
}
```
</details>

following GET parameters are supported to customize the returned properties:
- props[]=<DAV-prop-name> eg. props[]=getetag to return only the ETAG (multiple DAV properties can be specified)
  Default for calendar collections is to only return calendar-data (JsEvent), other collections return all props.
- sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT
- nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
  this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk

Examples: see addressbook


* **GET**  requests with an ```Accept: application/json``` header can be used to retrieve single resources / JsContact or JsCalendar schema
<details>
   <summary>Example: GET request for a single resource</summary>

```
curl 'https://example.org/egroupware/groupdav.php/calendar/6502' -H "Accept: application/pretty+json" --user <username>
{
    "@type": "Event",
    "prodId": "EGroupware Calendar 23.1.002",
    "uid": "calendar-5695-34b52fc11cfa7e9acea5732210a53f48",
    "sequence": "1",
    "created": "2023-07-14T06:05:53Z",
    "updated": "2023-07-14T08:00:04Z",
    "title": "Test",
    "start": "2023-07-14T10:00:00",
    "timeZone": "Europe/Berlin",
    "duration": "PT1H",
    "participants": {
        "5": {
            "@type": "Participant",
            "name": "Ralf Becker",
            "email": "ralf@boulder.egroupware.org",
            "kind": "individual",
            "roles": {
                "owner": true,
                "chair": true
            },
            "participationStatus": "accepted"
        }
    },
    "status": "confirmed",
    "priority": 5,
    "privacy": "public"
}
```
</details>

* **POST** requests to collection with a ```Content-Type: application/json``` header add new entries in addressbook or calendar collections
  (Location header in response gives URL of new resource)
<details>
   <summary>Example: POST request to create a new resource and use "Prefer: return=representation" to get it fully expanded back</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/calendar/' -X POST -d @- -H "Content-Type: application/json" -H "Prefer: return=representation" --user <username>
{                      
  "title": "Test 25th",
  "start": "2023-07-25T10:00:00",
  "timeZone": "Europe/Berlin",
  "duration": "PT1H" 
}
EOF

HTTP/1.1 201 Created
Content-Type: application/jscalendar+json;type=event;charset=utf-8
Location: /egroupware/groupdav.php/ralf/calendar/5704
ETag: "5704:0:1690209221"
Schedule-Tag: "5704:0"
X-WebDAV-Status: 201 Created

{
  "@type":"Event",
  "prodId":"EGroupware Calendar 23.1.002",
  "uid":"urn:uuid:e2b7278b-d91a-47d1-85ee-19dd1fb9b315",
  "created":"2023-07-24T14:33:41Z",
  "updated":"2023-07-24T14:33:41Z",
  "title":"Test 25th",
  "start":"2023-07-25T10:00:00",
  "timeZone":"Europe/Berlin",
  "duration":"PT1H",
  "participants":{
    "5":{
      "@type":"Participant",
      "name":"Ralf Becker",
      "email":"ralf@boulder.egroupware.org",
      "kind":"individual",
      "roles":{
        "owner":true,
        "chair":true
      },
      "participationStatus":"accepted"
    }
  }
  "status":"confirmed",
  "priority":5,
  "privacy":"public"
}
```
</details>

* **PUT**  requests with  a ```Content-Type: application/json``` header allow modifying single resources (requires to specify all attributes!)

<details>
   <summary>Example: PUT request with UID to update an existing resource or create it, if not exists</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/calendar/5638-8623c4830472a8ede9f9f8b30d435ea4' -X PUT -d @- -H "Content-Type: application/json" --user <username>
{
  "uid": "5638-8623c4830472a8ede9f9f8b30d435ea4",
  "title": "Testevent",
  "start": "2023-07-24T12:00:00",
  "timeZone": "Europe/Berlin",
  "duration": "PT2H",
....
}
EOF
```
Update of an existing one:
```
HTTP/1.1 204 No Content
```
New contact:
```
HTTP/1.1 201 Created
Location: https://example.org/egroupware/groupdav.php/<username>/calendar/1234
```
</details>


* **PATCH** request with a ```Content-Type: application/json``` header allow to modify a single resource by only specifying changed attributes as a [PatchObject](https://www.rfc-editor.org/rfc/rfc8984.html#type-PatchObject)

<details>
   <summary>Example: PATCH request to modify an event with partial data</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/calendar/1234' -X PATCH -d @- -H "Content-Type: application/json" --user <username>
{
  "title": "New title"
}
EOF

HTTP/1.1 204 No content
```
</details>

* **DELETE** requests delete single resources
<details>
   <summary>Example: Delete an existing event</summary>

> Please note: the "Accept: application/json" header is required, as the CalDAV server would return 404 NotFound as the url does NOT end with .ics

```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/calendar/1234' -X DELETE -H "Accept: application/json" --user <username>

HTTP/1.1 204 No Content
```
</details>

* one can use ```Accept: application/pretty+json``` to receive pretty-printed JSON e.g. for debugging and exploring the API