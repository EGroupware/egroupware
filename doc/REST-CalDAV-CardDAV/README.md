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
- ```/mail/```   REST API only
- ```/timesheet/```   REST API only

Shared addressbooks or calendars are only shown in the users home-set, if he subscribed to it via his CalDAV preferences!

Calling one of the above collections with a GET request / regular browser generates an automatic index
from the data of an ```allprop``` PROPFIND, allow browsing CalDAV/CardDAV tree with a regular browser.

## REST API: using EGroupware CalDAV/CardDAV server with JSON
- [Addressbook](Addressbook.md)
- [Calendar](Calendar.md) 
  * currently recurring events are readonly, they are returned but can not be created or modified
- [Mail](Mail.md) 
  * currently only sending mails, 
  * opening interactive compose windows, 
  * view and reply to eml files and 
  * vacation handling
- [Timesheet](Timesheet.md)
- [Links and attachments](Links-and-attachments.md)
  * linking application entries to other application entries
  * attaching files to application entries
  * listing, creating and deleting links and attachments

> For the REST API you always have to send an "Accept: application/json" header and for POST & PUT requests additionally 
> a "Content-Type: application/json" header, otherwise you talk to the CalDAV/CardDAV server and don't get the response you expect!

Following RFCs / drafts used/planned for JSON encoding of resources
* [draft-ietf-jmap-jscontact: JSContact: A JSON Representation of Contact Data](https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact) 
([* see at end of document](#implemented-changes-from-jscontact-draft-08))
* [draft-ietf-jmap-jscontact-vcard: JSContact: Converting from and to vCard](https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-vcard/)
* [rfc8984: JSCalendar: A JSON Representation of Calendar Data](https://datatracker.ietf.org/doc/html/rfc8984)

### ToDos
- [x] Addressbook
  - [ ] update of photos, keys, attachments
- [ ] InfoLog
- [X] Calendar (recurring events and alarms are readonly)
  - [ ] support creating and modifying recurring events and alarms
- [X] Mail
  - [ ] querying received mails
- [ ] relatedTo / links
- [ ] storing not native supported attributes eg. localization