# EGroupware CalDAV/CardDAV server and REST API

CalDAV/CardDAV is build on HTTP and WebDAV, implementing the following additional RFCs containing documentation of the protocol:
* [rfc4791: CalDAV: Calendaring Extensions to WebDAV](https://datatracker.ietf.org/doc/html/rfc4791)
* [rfc6638: Scheduling Extensions to CalDAV](https://datatracker.ietf.org/doc/html/rfc6638)
* [rfc6352: CardDAV: vCard Extensions to WebDAV](https://datatracker.ietf.org/doc/html/rfc6352)
* [rfc6578: Collection Synchronization for WebDAV](https://datatracker.ietf.org/doc/html/rfc6578)
* many additional extensions from former Apple Calendaring Server used by Apple clients and others

## Path / URL layout for CalDAV/CardDAV and REST is identical

One can use the following URLs relative (!) to https://example.org/egroupware/groupdav.php

- ```/```                        base of Cal|Card|GroupDAV tree, only certain clients (KDE, Apple) can autodetect folders from here
- ```/principals/```             principal-collection-set for WebDAV ACL
- ```/principals/users/<username>/```
- ```/principals/groups/<groupname>/```
- ```/<username>/```             users home-set with
- ```/<username>/addressbook/``` addressbook of user or group <username> given the user has rights to view it
- ```/<current-username>/addressbook-<other-username>/``` shared addressbooks from other user or group
- ```/<current-username>/addressbook-accounts/``` all accounts current user has rights to see
- ```/<username>/calendar/```    calendar of user <username> given the user has rights to view it
- ```/<username>/calendar/?download``` download whole calendar as .ics file (GET request!)
- ```/<current-username>/calendar-<other-username>/``` shared calendar from other user or group (only current <username>!)
- ```/<username>/inbox/```       scheduling inbox of user <username>
- ```/<username>/outbox/```      scheduling outbox of user <username>
- ```/<username>/infolog/```     InfoLog's of user <username> given the user has rights to view it
- ```/addressbook/``` all addressbooks current user has rights to, announced as directory-gateway now
- ```/addressbook-accounts/``` all accounts current user has rights to see
- ```/calendar/```    calendar of current user
- ```/infolog/```     infologs of current user
- ```/(resources|locations)/<resource-name>/calendar``` calendar of a resource/location, if user has rights to view
- ```/<current-username>/(resource|location)-<resource-name>``` shared calendar from a resource/location

Shared addressbooks or calendars are only shown in the users home-set, if he subscribed to it via his CalDAV preferences!

Calling one of the above collections with a GET request / regular browser generates an automatic index
from the data of a allprop PROPFIND, allow browsing CalDAV/CardDAV tree with a regular browser.

## REST API: using EGroupware CalDAV/CardDAV server with JSON
> currently implemented only for contacts!

Following RFCs / drafts used/planned for JSON encoding of ressources
* [draft-ietf-jmap-jscontact: JSContact: A JSON Representation of Contact Data](https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact) 
([* see at end of document](#implemented-changes-from-jscontact-draft-08))
* [draft-ietf-jmap-jscontact-vcard: JSContact: Converting from and to vCard](https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-vcard/)
* [rfc8984: JSCalendar: A JSON Representation of Calendar Data](https://datatracker.ietf.org/doc/html/rfc8984)

### Supported request methods and examples

* **GET** to collections with an ```Accept: application/json``` header return all resources (similar to WebDAV PROPFIND)
<details>
  <summary>Example: Getting all entries of a given users addessbook</summary>
  
```
curl https://example.org/egroupware/groupdav.php/<username>/addressbook/ -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/<username>/addressbook/1833": {
      "uid": "5638-8623c4830472a8ede9f9f8b30d435ea4",
      "prodId": "EGroupware Addressbook 21.1.001",
      "created": "2010-10-21T09:55:42Z",
      "updated": "2014-06-02T14:45:24Z",
      "name": [
        { "@type": "NameComponent", "type": "personal", "value": "Default" },
        { "@type": "NameComponent", "type": "surname", "value": "Tester" }
      ],
      "fullName": { "value": "Default Tester" },
      "organizations": {
        "org": {
          "@type": "Organization", 
          "name": "default.org",
          "units": {
            "org_unit": "department.default.org"
          }
        }
      },
      "emails": {
        "work": { "@type": "EmailAddress", "email": "test@test.com", "contexts": { "work": true }, "pref": 1 }
      },
      "phones": {
        "tel_work": { "@type": "Phone", "phone": "+49 123 4567890", "pref": 1, "features": { "voice": true }, "contexts": { "work": true } },
        "tel_cell": { "@type": "Phone", "phone": "012 3723567", "features": { "cell": true }, "contexts": { "work": true } }
      },
      "online": {
        "url": { "@type": "Resource", "resource": "https://www.test.com/", "type": "uri", "contexts": { "work": true } }
      },
      "notes": [
        "Test test TEST\n\\server\\share\n\\\nother\nblah"
      ],
    },
    "/<username>/addressbook/list-36": {
      "uid": "urn:uuid:dfa5cac5-987b-448b-85d7-6c8b529a835c",
      "name": "Example distribution list",
      "card": {
        "uid": "urn:uuid:dfa5cac5-987b-448b-85d7-6c8b529a835c",
        "prodId": "EGroupware Addressbook 21.1.001",
        "updated": "2018-04-11T14:46:43Z",
        "fullName": { "value": "Example distribution list" }
      },
      "members": {
        "5638-8623c4830472a8ede9f9f8b30d435ea4": true
      }
    }
  }
}
```
</details>
       
  following GET parameters are supported to customize the returned properties:
  - props[]=<DAV-prop-name> eg. props[]=getetag to return only the ETAG (multiple DAV properties can be specified)
    Default for addressbook collections is to only return address-data (JsContact), other collections return all props.
  - sync-token=<token> to only request change since last sync-token, like rfc6578 sync-collection REPORT
  - nresults=N limit number of responses (only for sync-collection / given sync-token parameter!)
    this will return a "more-results"=true attribute and a new "sync-token" attribute to query for the next chunk

<details>
   <summary>Example: Getting just ETAGs and displayname of all contacts in a given AB</summary>
   
```
curl -i 'https://example.org/egroupware/groupdav.php/<username>/addressbook/?props[]=getetag&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/addressbook/1833": {
      "displayname": "Default Tester",
      "getetag": "\"1833:24\""
    },
    "/addressbook/1838": {
      "displayname": "Test Tester",
      "getetag": "\"1838:19\""
    }
  }
}
```
</details>

<details>
   <summary>Example: Start using a sync-token to get only changed entries since last sync</summary>
   
#### Initial request with empty sync-token and only requesting 10 entries per chunk:
```
curl 'https://example.org/egroupware/groupdav.php/addressbook/?sync-token=&nresults=10&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/addressbook/2050": "Frau Margot Test-Notifikation",
    "/addressbook/2384": "Test Tester",
    "/addressbook/5462": "Margot Testgedöns",
    "/addressbook/2380": "Frau Test Defaulterin",
    "/addressbook/5474": "Noch ein Neuer",
    "/addressbook/5575": "Mr New Name",
    "/addressbook/5461": "Herr Hugo Kurt Müller Senior",
    "/addressbook/5601": "Steve Jobs",
    "/addressbook/5603": "Ralf Becker",
    "/addressbook/1838": "Test Tester"
  },
  "more-results": true,
  "sync-token": "https://example.org/egroupware/groupdav.php/addressbook/1400867824"
}
```
#### Requesting next chunk:
```
curl 'https://example.org/egroupware/groupdav.php/addressbook/?sync-token=https://example.org/egroupware/groupdav.php/addressbook/1400867824&nresults=10&props[]=displayname' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/addressbook/1833": "Default Tester",
    "/addressbook/5597": "Neuer Testschnuffi",
    "/addressbook/5593": "Muster Max",
    "/addressbook/5628": "2. Test Contact",
    "/addressbook/5629": "Testen Tester",
    "/addressbook/5630": "Testen Tester",
    "/addressbook/5633": "Testen Tester",
    "/addressbook/5635": "Test4 Tester",
    "/addressbook/5638": "Test Kontakt",
    "/addressbook/5636": "Test Default"
  },
  "more-results": true,
  "sync-token": "https://example.org/egroupware/groupdav.php/addressbook/1427103057"
}
```
</details>

<details>
   <summary>Example: Requesting only changes since last sync</summary>
   
#### ```sync-token``` from last sync need to be specified (note the null for a deleted resource!)
```
curl 'https://example.org/egroupware/groupdav.php/addressbook/?sync-token=https://example.org/egroupware/groupdav.php/addressbook/1400867824' -H "Accept: application/pretty+json" --user <username>
{
  "responses": {
    "/addressbook/5597": null,
    "/addressbook/5593": {
      "uid": "5638-8623c4830472a8ede9f9f8b30d435ea4",
      "prodId": "EGroupware Addressbook 21.1.001",
      "created": "2010-10-21T09:55:42Z",
      "updated": "2014-06-02T14:45:24Z",
      "name": [
        { "@type": "NameComponent", "type": "personal", "value": "Default" },
        { "@type": "NameComponent", "type": "surname", "value": "Tester" }
      ],
      "fullName": "Default Tester",
....
    }
  },
  "sync-token": "https://example.org/egroupware/groupdav.php/addressbook/1427103057"
}
```
</details>

* **GET**  requests with an ```Accept: application/json``` header can be used to retrieve single resources / JsContact or JsCalendar schema
<details>
   <summary>Example: GET request for a single resource showcasing available fieldes</summary>
   
```
curl 'https://example.org/egroupware/groupdav.php/addressbook/6502' -H "Accept: application/pretty+json" --user <username>
{
    "uid": "addressbook-6502-8623c4830472a8ede9f9f8b30d435ea4",
    "prodId": "EGroupware Addressbook 21.1.003",
    "created": "2022-12-14T13:35:02Z",
    "updated": "2022-12-14T13:39:14Z",
    "kind": "individual",
    "name": [
        { "@type": "NameComponent", "type": "prefix", "value": "Prefix/Title" },
        { "@type": "NameComponent", "type": "personal", "value": "Frist" },
        { "@type": "NameComponent", "type": "additional", "value": "Middle" },
        { "@type": "NameComponent", "type": "surname", "value": "Last" },
        { "@type": "NameComponent", "type": "suffix", "value": "Postfix" }
    ],
    "fullName": "Prefix/Title Frist Middle Last Postfix",
    "organizations": {
        "org": {
            "@type": "Organization",
            "name": "Organisation",
            "units": { "org_unit": "Department" }
        }
    },
    "titles": {
        "title": {
            "@type": "Title",
            "title": "Postion",
            "organization": "org"
        },
        "role": {
            "@type": "Title",
            "title": "Occupation",
            "organization": "org"
        }
    },
    "emails": {
        "work": {
            "@type": "EmailAddress",
            "email": "email@example.org",
            "contexts": { "work": true },
            "pref": 1
        },
        "private": {
            "@type": "EmailAddress",
            "email": "private.email@example.org",
            "contexts": { "private": true }
        }
    },
    "phones": {
        "tel_work": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "voice": true },
            "contexts": { "work": true }
        },
        "tel_cell": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "cell": true },
            "contexts": { "work": true }
        },
        "tel_fax": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "fax": true },
            "contexts": { "work": true }
        },
        "tel_assistent": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "voice": true },
            "contexts": { "assistant": true }
        },
        "tel_car": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "voice": true },
            "contexts": { "car": true }
        },
        "tel_pager": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "pager": true },
            "contexts": { "work": true }
        },
        "tel_home": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "voice": true },
            "contexts": { "private": true }
        },
        "tel_fax_home": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "fax": true },
            "contexts": { "private": true }
        },
        "tel_cell_private": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "cell": true },
            "contexts": { "private": true }
        },
        "tel_other": {
            "@type": "Phone",
            "phone": "+1(234)5678901",
            "features": { "voice": true },
            "contexts": { "work": true }
        }
    },
    "online": {
        "url": {
            "@type": "Resource",
            "resource": "https://example.org",
            "type": "uri",
            "contexts": { "work": true }
        },
        "url_home": {
            "@type": "Resource",
            "resource": "https://private.example.org",
            "type": "uri",
            "contexts": { "private": true }
        }
    },
    "addresses": {
        "work": {
            "@type": "Address",
            "locality": "City",
            "region": "Rheinland-Pfalz",
            "country": "DEUTSCHLAND",
            "postcode": "12345",
            "countryCode": "DE",
            "street": [
                { "@type": "StreetComponent", "type": "name", "value": "Street" },
                { "@type": "StreetComponent", "type": "separator", "value": "\n" },
                { "@type": "StreetComponent", "type": "name", "value": "Street2" ],
            "contexts": { "work": true },
            "pref": 1
        },
        "home": {
            "@type": "Address",
            "locality": "PrivateCity",
            "country": "DEUTSCHLAND",
            "postcode": "12345",
            "countryCode": "DE",
            "street": [
                { "@type": "StreetComponent", "type": "name", "value": "PrivateStreet" },
                { "@type": "StreetComponent", "type": "separator", "value": "\n" },
                { "@type": "StreetComponent", "type": "name", "value": "PrivateStreet2" }
            ],
            "contexts": { "home": true }
        }
    },
    "photos": {
        "photo": {
            "@type": "File",
            "href": "https://boulder.egroupware.org/egroupware/api/avatar.php?contact_id=6502&lavatar=1&etag=0",
            "mediaType": "image/jpeg"
        }
    },
    "anniversaries": {
        "bday": {
            "@type": "Anniversary",
            "type": "birth",
            "date": "2022-12-14"
        }
    },
    "categories": {
        "Kategorie": true,
        "My Contacts": true
    },
    "egroupware.org:assistant": "Assistent"
}
```
</details>

* **POST** requests to collection with a ```Content-Type: application/json``` header add new entries in addressbook or calendar collections
       (Location header in response gives URL of new resource)
<details>
   <summary>Example: POST request to create a new resource</summary>
   
```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/addressbook/' -X POST -d @- -H "Content-Type: application/json" --user <username>
{
  "uid": "5638-8623c4830472a8ede9f9f8b30d435ea4",
  "prodId": "EGroupware Addressbook 21.1.001",
  "created": "2010-10-21T09:55:42Z",
  "updated": "2014-06-02T14:45:24Z",
  "name": [
    { "type": "@type": "NameComponent", "personal", "value": "Default" },
    { "type": "@type": "NameComponent", "surname", "value": "Tester" }
  ],
  "fullName": { "value": "Default Tester" },
....
}
EOF

HTTP/1.1 201 Created
Location: https://example.org/egroupware/groupdav.php/<username>/addressbook/1234
```
</details>

<details>
   <summary>Example: POST request to create a new resource using flat attributes (JSON patch syntax) eg. for a simple Wordpress contact-form</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/addressbook/' -X POST -d @- -H "Content-Type: application/json" --user <username>
{
  "fullName": "First Tester",
  "name/personal": "First",
  "name/surname":  "Tester",
  "organizations/org/name": "Test Organization",
  "emails/work": "test.user@test-user.org",
  "addresses/work/locality": "Test-Town",
  "addresses/work/postcode": "12345",
  "addresses/work/street": "Teststr. 123",
  "addresses/work/country": "Germany",
  "addresses/work/countryCode": "DE",
  "phones/tel_work": "+49 123 4567890",
  "online/url": "https://www.example.org/",
  "notes/note": "This is a note.",
  "egroupware.org:customfields/Test": "Content for Test"
}
EOF

HTTP/1.1 201 Created
Location: https://example.org/egroupware/groupdav.php/<username>/addressbook/1234
```
</details>

* **PUT**  requests with  a ```Content-Type: application/json``` header allow modifying single resources (requires to specify all attributes!)

<details>
   <summary>Example: PUT request to update a resource</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/addressbook/1234' -X PUT -d @- -H "Content-Type: application/json" --user <username>
{
  "uid": "5638-8623c4830472a8ede9f9f8b30d435ea4",
  "prodId": "EGroupware Addressbook 21.1.001",
  "created": "2010-10-21T09:55:42Z",
  "updated": "2014-06-02T14:45:24Z",
  "name": [
    { "type": "@type": "NameComponent", "personal", "value": "Default" },
    { "type": "@type": "NameComponent", "surname", "value": "Tester" }
  ],
  "fullName": { "value": "Default Tester" },
....
}
EOF

HTTP/1.1 204 No Content
```
</details>

<details>
   <summary>Example: PUT request with UID to update an existing resource or create it, if not exists</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/addressbook/5638-8623c4830472a8ede9f9f8b30d435ea4' -X PUT -d @- -H "Content-Type: application/json" --user <username>
{
  "uid": "5638-8623c4830472a8ede9f9f8b30d435ea4",
  "prodId": "EGroupware Addressbook 21.1.001",
  "created": "2010-10-21T09:55:42Z",
  "updated": "2014-06-02T14:45:24Z",
  "name": [
    { "type": "@type": "NameComponent", "personal", "value": "Default" },
    { "type": "@type": "NameComponent", "surname", "value": "Tester" }
  ],
  "fullName": { "value": "Default Tester" },
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
Location: https://example.org/egroupware/groupdav.php/<username>/addressbook/1234
```
</details>


* **PATCH** request with a ```Content-Type: application/json``` header allow to modify a single resource by only specifying changed attributes as a [PatchObject](https://www.rfc-editor.org/rfc/rfc8984.html#type-PatchObject)

<details>
   <summary>Example: PATCH request to modify a contact with partial data</summary>

```
cat <<EOF | curl -i 'https://example.org/egroupware/groupdav.php/<username>/addressbook/1234' -X PATCH -d @- -H "Content-Type: application/json" --user <username>
{
  "name": [
    {
      "@type": "NameComponent",
      "type": "personal",
      "value": "Testfirst"
    },
    {
      "@type": "NameComponent",
      "type": "surname",
      "value": "Username"
    }
  ],
  "fullName": "Testfirst Username",
  "organizations/org/name": "Test-User.org",
  "emails/work/email": "test.user@test-user.org"
}
EOF

HTTP/1.1 204 No content
```
</details>

* **DELETE** requests delete single resources

* one can use ```Accept: application/pretty+json``` to receive pretty-printed JSON eg. for debugging and exploring the API

#### Implemented [changes from JsContact draft 08](https://github.com/rsto/draft-stepanek-jscontact/compare/draft-ietf-jmap-jscontact-08):
* localizedString type / object is removed in favor or regular String type and a [localizations object like in JsCalendar](https://datatracker.ietf.org/doc/html/rfc8984#section-4.6.1)
* [Vendor-specific Property Extensions and Values](https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-1.3) 
use ```<domain-name>:<name>``` like in JsCalendar
* top-level objects need a ```@type``` attribute with one of the following values: 
```NameComponent```, ```Organization```, ```Title```, ```Phone```, ```Resource```, ```File```, ```ContactLanguage```, 
```Address```, ```StreetComponent```, ```Anniversary```, ```PersonalInformation```

### ToDos
- [x] Addressbook
  - [ ] update of photos, keys, attachments
- [ ] InfoLog
- [ ] Calendar
- [ ] relatedTo / links
- [ ] storing not native supported attributes eg. localization
