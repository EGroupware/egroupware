# n8n: support mail -> Tracker tickets

[n8n](https://n8n.io) is a workflow automation tool. `mail-to-tracker.workflow.json` is an example
workflow that turns a support mailbox into EGroupware Tracker tickets, talking to EGroupware only
through its REST APIs ([Mail](../REST-CalDAV-CardDAV/Mail.md),
[Tracker](../REST-CalDAV-CardDAV/Tracker.md)):

1. **Read new mail** - every minute it lists the INBOX of one mail account, skips every mail that
   already has a ticket and fetches the body of the new ones.
2. **Answer to an existing ticket** - a subject carrying `[#<id>]` (eg. `Re: [#42] Printer broken`)
   is added as a reply to ticket 42, never as a second ticket.
3. **New ticket - AI triage** - an LLM picks the queue, the priority (by business impact, not by
   how loud the sender is) and a short English title, whatever language the mail is written in.
   The ticket gets that triage on top and the original mail below it. If the LLM is unreachable,
   simple keyword rules decide instead.
4. **Attachments** - files from the mail are linked to the new ticket.

## In the development environment

[`doc/docker/development/docker-compose.yml`](../docker/development/docker-compose.yml) runs n8n
as `http://localhost/n8n/`. Like push, start it once EGroupware is installed.

Put these into a `.env` file next to `docker-compose.yml` **before n8n starts for the first time**:

```
EGW_N8N_USER=sysop
EGW_N8N_PASSWORD=<password or app password of that user>
EGW_N8N_MAIL_ACCOUNT=<id of the mail account to read, see below>
EGW_N8N_AI_KEY=<key for https://ai-proxy.egroupware.org/v1>
```

On its first start the container then imports the workflow, creates its two credentials and
activates it: from then on every mail arriving in that INBOX becomes a ticket within a minute, with
no click in n8n. Open `http://localhost/n8n/` and create n8n's owner account to watch the workflow
and its executions.

The mail account id is the number at the end of the path returned by
`GET /egroupware/groupdav.php/<user>/mail/` - an identity id, see the Mail REST API.

Optional variables:

| Variable | Default | |
|---|---|---|
| `EGW_N8N_URL` | `http://nginx/egroupware` | EGroupware as seen from the n8n container |
| `EGW_N8N_QUEUES` | `{"Bugs": 8, "Feature Requests": 7, "Patches": 9}` | tracker ids of the LLM's queue labels, the default matches a new installation, check yours in Admin > Tracker |
| `EGW_N8N_LLM_URL` | `https://ai-proxy.egroupware.org/v1/chat/completions` | any OpenAI compatible chat completion endpoint |
| `EGW_N8N_LLM_MODEL` | `mistralai/Mistral-Small-24B-Instruct` | |

Without the four variables the workflow is still imported, but inactive. To finish it by hand in
n8n: create an *HTTP Basic Auth* credential "EGroupware REST API" (EGroupware user and password)
and an *HTTP Header Auth* credential "EGroupware AI proxy" (name `Authorization`, value
`Bearer <key>`), select them in the HTTP nodes, set `mailAccount` in the *Config* node and publish
the workflow.

The import runs only once, so changes made in the n8n editor survive restarts. To start over,
remove the container and its volume: `docker-compose rm -sf n8n`, then
`docker volume rm <project>_n8n`.

## With any other n8n

Import `mail-to-tracker.workflow.json` (Workflows > Import from file), create the two credentials
as above and set your EGroupware's `groupdav.php` URL, user, mail account and queue ids in the
*Config* node.

## Good to know

- The mail REST API is read-only, so a handled mail can not be flagged as seen. The
  `Mail: <account>:<folder>:<uid>` line in the ticket description is what keeps a mail from being
  filed twice - and on the very first run only mail of the last hour is considered, so pointing
  the workflow at a full mailbox does not turn all of it into tickets.
- Every new mail becomes a ticket, there is no "is this a support request at all" filter: use a
  dedicated support mailbox.
- The subject and body of each new mail are sent to the configured LLM.
- n8n is "fair-code" under its [Sustainable Use License](https://docs.n8n.io/sustainable-use-license/),
  not OSI open source: fine for your own internal use and for development, check the license
  before offering n8n itself as a service to others.
