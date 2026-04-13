# EGroupware OpenAPI for OpenWebUI

## Tool Inventory

Below is a quick‑ref cheat sheet of all the EGroupware‑powered functions. Each name is a tool you can invoke; most of them are CRUD operations for contacts, calendar events, tasks, mail, knowledge bases, and more.


| Tool | Purpose | Typical Parameters (examples) |
| --- | --- | --- |
| `listAllContacts` | Get every contact you can see | `Accept: application/json` |
| `listContacts` | Get your personal address-book | `Accept: application/json` |
| `createContact` | Add a new contact | `fullName`, `name[]`, `emails[]`, `phones[]`, etc. |
| `getContact` | Retrieve a single contact by ID | `Accept: application/json` |
| `replaceContact` | Overwrite all fields of a contact | Same as create, but `uid` provided |
| `updateContact` | Partial update / patch | For example `fullName: New Name`, `phones/tel_work: +123...` |
| `deleteContact` | Delete a contact | None |
| `listEvents / listUserEvents` | List all events or user-specific events | `Accept: application/json` |
| `createEvent` | Create a new event | `title`, `start`, `duration`, `timeZone`, etc. |
| `getEvent` | Retrieve a single event | `Accept: application/json` |
| `replaceEvent` | Full replacement of an event | Full event payload |
| `updateEvent` | Patch specific fields | For example `title: Updated`, `status: confirmed` |
| `deleteEvent` | Delete an event | `Accept: application/json` |
| `listTasks / listUserTasks` | Fetch all infolog items such as tasks and notes | `Accept: application/json` |
| `createTask` | Add a new task, note, or similar entry | `title`, `type`, `description`, etc. |
| `getTask` | Retrieve a single infolog entry | `Accept: application/json` |
| `replaceTask` | Full replacement | Full task payload |
| `updateTask` | Patch a task | `status: completed`, etc. |
| `deleteTask` | Delete an infolog entry | None |
| `listMailAccounts` | Get mail identities and signatures | `Accept: application/json` |
| `sendMail` | Send a plain text or HTML email through the default identity | `to`, `subject`, `body`, `cc`, `bcc` |
| `sendMailFor` | Send email using a specific identity | Same as above, but via account ID |
| `launchMailCompose` | Open an interactive compose window | Same parameters as mail send |
| `uploadMailAttachment` | Upload a file for attachment or later use | `filename`, `Content-Type` |
| `getVacation` | View vacation mail settings | `Accept: application/json` |
| `setVacation` | Configure vacation auto-reply | `status`, `text`, `start`, `end`, etc. |
| `searchRAG` | Full-text, RAG, or hybrid search in app data | `filters[search]: query` |
| `listTimesheets` | List all timesheet entries | `Accept: application/json` |
| `createTimesheet` | Log a new timesheet | `start`, `duration`, `title`, etc. |
| `getTimesheet` | Retrieve a single timesheet | `Accept: application/json` |
| `replaceTimesheet` | Full update of a timesheet | Full timesheet payload |
| `updateTimesheet` | Partial update | Partial timesheet fields |
| `deleteTimesheet` | Delete a timesheet | None |
| `get_current_timestamp` | Get current Unix time in seconds | None |
| `calculate_timestamp` | Compute timestamps relative to now | `days_ago`, `weeks_ago`, etc. |
| `list_knowledge_bases` | List all knowledge bases you can access | `count`, `skip` |
| `search_knowledge_bases` | Search knowledge bases by name or description | `query`, `count`, `skip` |
| `query_knowledge_bases` | Semantic search for relevant knowledge bases | `query`, `count` |
| `search_knowledge_files` | Search files by filename | `query`, `count`, `knowledge_id`, `skip` |
| `query_knowledge_files` | Semantic search within knowledge-base files | `query`, `knowledge_ids`, `count` |
| `view_knowledge_file` | Retrieve a knowledge file's content | `file_id`, `max_chars`, `offset` |
| `search_chats` | Search previous chat transcripts | `query`, `count`, `start_timestamp`, `end_timestamp` |
| `view_chat` | Get the full history of a chat | `chat_id` |


## Example

Use the tools in OpenWebUI with short prompts like these:

```text
/listAllContacts
Get every contact you can see.

/listContacts
Get your personal address book.

/createContact fullName="John Example" emails[]="john@example.com"
Create a new contact.

/listEvents
List all of my upcoming events.

/createEvent title="Project Sync" start="2026-04-13T09:00:00Z" duration="PT1H"
Create a one-hour calendar event.

/listTasks
Fetch all my tasks and notes.

/updateTask id="12345" status="completed"
Mark a task as completed.

/sendMail to="team@example.com" subject="Weekly Update" body="Here is the latest status."
Send an email with the default mail identity.

/searchRAG filters[search]="contract renewal"
Search the RAG index for matching records.

/createTimesheet title="OpenAPI documentation" start="2026-04-13T08:00:00Z" duration="PT90M"
Create a 90-minute timesheet entry.
```
