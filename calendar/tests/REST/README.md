# Calendar REST API tests

REST (JSON / [JsCalendar](https://datatracker.ietf.org/doc/html/rfc8984)) counterparts of the
CalDAV tests in [`../CalDAV`](../CalDAV). They exercise the same EGroupware `groupdav.php`
endpoint, but use the REST API instead of WebDAV/CalDAV requests.

See [`doc/REST-CalDAV-CardDAV/Calendar.md`](../../../doc/REST-CalDAV-CardDAV/Calendar.md) for the
REST API documentation.

## How REST differs from CalDAV here

| | CalDAV | REST |
|---|---|---|
| Content | `text/calendar` (iCalendar) | `application/json` (JsCalendar) |
| Create | `PUT /<user>/calendar/<uid>.ics` | `POST /<user>/calendar/` (Location/ETag give the new id) |
| Resource URL | `/<user>/calendar/<uid>.ics` | `/<user>/calendar/<cal_id>` (numeric id, no `.ics`) |
| Read | `GET …` returns iCal | `GET …` with `Accept: application/json` returns JsEvent |
| Delete | `DELETE …` | `DELETE …` **must** send `Accept: application/json` |
| Discovery | `PROPFIND` on principal | `GET` on the collection |

The server tells REST and CalDAV requests apart via the `Content-Type` (POST/PUT/PATCH) or
`Accept` (GET/DELETE) header — see `Api\CalDAV::isJSON()`.

## Base class

The shared base class is [`api/tests/RestBase.php`](../../../api/tests/RestBase.php). It extends
`Api\CalDAVTest` to reuse the user/ACL setup, authentication, cleanup and HTTP-status assertions,
and adds JSON request/assertion helpers (`postEvent()`, `putEventJson()`, `patchEventJson()`,
`getEventJson()`, `deleteEvent()`, `assertParticipationStatus()`, …).

## Tests in this directory

- `CreateReadDeleteTest` — create (POST), read (GET), delete (DELETE) and the no-auth/404 cases.
- `PutPreconditionTest` — `If-Match` / `If-Schedule-Tag-Match` preconditions on PUT.
- `PutValidationAclTest` — malformed-JSON rejection and foreign-calendar ACL denial.
- `SingleDeleteTest` — organizer/attendee delete & reject semantics incl. the
  `User-Agent: CalDAVSynchronizer` special case.

## Not yet covered

The CalDAV `IcalSyncTest` and `RecurrenceExceptionParticipantTest` are **not** adapted here yet,
because the REST API currently rejects creating/modifying recurring events
(`JsCalendar::parseEvent()` throws *"Creating or modifying recurring events is NOT (yet)
implemented!"* for `recurrenceRules` / `recurrenceOverrides`). Once that is implemented — or as
*not-implemented* assertions — they can be added.

## Running

```
vendor/bin/phpunit -c doc/phpunit.xml --filter 'EGroupware\\calendar\\REST'
```

They are integration tests and need a running EGroupware instance reachable at `EGW_URL`
(see `doc/phpunit.xml`), exactly like the CalDAV tests.
