# Fastmail: OAuth access for the Mail Wizard

## Status: paused 2026-08-26, waiting on a manually-provisioned OAuth client_id from Fastmail
(ralf emailed Bron, Fastmail's CEO, a CalConnect contact, to ask directly). No code changed yet -
this doc records what was found while scoping the work, so it can restart cleanly once a
client_id exists.

This is the concrete, Fastmail-scoped continuation of [[mail-wizard-jmap-oauth]]'s Milestone B
item 2 ("broader OAuth support, generalize past the hardcoded Google/Microsoft domain-regex
table") - ralf already has a real Fastmail test account to validate against.

## Symptom that started this (live, via the wizard, `fastmail.com` email/password)

```
Benutze Mozilla ISPDB für Provider Fastmail
16:54:07: Trying JMAP connection to https://api.fastmail.com ...
EGroupware\Api\Exception\Http: Unexpected HTTP status code 401 :
  Bearer resource_metadata="https://api.fastmail.com/.well-known/oauth-protected-resource/jmap/session"
16:54:09: Trying TLS connection to imap.fastmail.com:993 ...
Mail server denied authentication.
```

Fastmail no longer accepts the account password directly for JMAP or IMAP login - both need
OAuth. `.well-known/openid-configuration` looked empty to ralf on first check (wrong host tried
at the time); it does exist on the right host, see below.

## Findings (all confirmed live, 2026-08-26)

- **JMAP host discovery already works, zero new code needed.** `dig SRV _jmap._tcp.fastmail.com`
  → `api.fastmail.com:443`, exactly matching the wizard's log line above - Milestone A.1's SRV
  discovery in `tryJmap()` (`admin/inc/class.admin_mail.inc.php`) already finds Fastmail's JMAP
  endpoint correctly on its own.
- **RFC 9728 Protected Resource Metadata** at
  `https://api.fastmail.com/.well-known/oauth-protected-resource/jmap/session` (the URL from the
  401's `WWW-Authenticate: Bearer resource_metadata="..."` header) returns
  `authorization_servers: ["https://api.fastmail.com"]` plus the supported scopes
  (`urn:ietf:params:oauth:scope:mail`, `...:contacts`, `...:calendars`, `offline_access`).
- **RFC 8414 / OIDC discovery works on `api.fastmail.com`** - both
  `/.well-known/oauth-authorization-server` and `/.well-known/openid-configuration` return full
  metadata: `authorization_endpoint` (`/oauth/authorize`), `token_endpoint` (`/oauth/refresh`,
  unusual name but standard shape), `registration_endpoint` (`/oauth/register`),
  `revocation_endpoint`, `code_challenge_methods_supported: ["S256"]`, and critically
  `token_endpoint_auth_methods_supported: ["none"]` - Fastmail's OAuth client is a **public
  PKCE client, no client secret at all**, unlike Google/Microsoft's confidential-client model.
- **Classic IMAP and SMTP both support OAuth too, not just JMAP** - confirmed via a direct raw
  protocol handshake (no credentials sent): `imap.fastmail.com:993` CAPABILITY includes
  `AUTH=XOAUTH2 AUTH=OAUTHBEARER`; `smtp.fastmail.com:465` EHLO includes the same. This means
  once a client_id exists, the *existing* `Horde_Imap_Client_Password_Xoauth2`/
  `Horde_Smtp_Password_Xoauth2` plumbing (already used for Google/Microsoft, see
  `admin_mail::oauthToken()`) should work for Fastmail unchanged - no JMAP-only limitation, no new
  IMAP/SMTP-side code.
- **RFC 7591 Dynamic Client Registration is live and unauthenticated**
  (`POST https://api.fastmail.com/oauth/register`) - but it only accepted loopback redirect URIs
  (`http://localhost/callback`, `http://127.0.0.1/callback`, both got `201` + a real `client_id`
  back) and **rejected our actual production redirect**,
  `https://proxy.egroupware.org/oauth` (the single shared redirect URL all EGroupware installs
  use for Google/Microsoft too, see `OpenIDConnectClient::EGROUPWARE_OAUTH_PROXY`), with
  `400 invalid_redirect_uri`. Read as: Fastmail's dynamic registration is scoped to native/
  loopback public clients, not the shared-web-redirect model our OAuth integration is built on -
  self-service dynamic registration is very unlikely to produce a client_id we can actually use.
  This is why ralf is going the manual-registration route via Bron instead of waiting on further
  API probing.

## What's needed once a client_id exists (not yet implemented)

1. **Add a Fastmail entry** to `OpenIDConnectClient::$oauth_domain_regexps`
   (`api/src/Auth/OpenIDConnectClient.php:48`), mirroring the existing Google/Microsoft entries:
   - email-regexp covering Fastmail's own vanity domains (`fastmail.com`, `fastmail.fm`, and the
     other domains Fastmail owns)
   - a `server_regexp` fallback matching `imap.fastmail.com`/`smtp.fastmail.com`/
     `api.fastmail.com` (same mechanism Google uses for `imap.gmail.com`), so custom domains
     hosted on Fastmail are still recognized once a host is discovered, even though their email
     address itself won't match the regexp
   - provider `api.fastmail.com`, client = the new client_id, **secret = null/empty**
   - scopes: `urn:ietf:params:oauth:scope:mail offline_access email` (`email` for the existing
     `getVerifiedClaims('email')` identification fallback in `oauthAuthenticated()`)
2. **Two real code fixes are needed for a secret-less (PKCE) provider** - found while scoping
   this, not yet applied:
   - `admin_mail::oauthToken()` (`admin/inc/class.admin_mail.inc.php:2617`) currently keys its
     "do we already have OAuth config" check AND its `throw new Exception("No OAuth client secret
     for provider...")` guard purely on `empty($content['acc_oauth_client_secret'])` - that's
     exactly what a legitimately-empty PKCE secret looks like, so Fastmail would always hit the
     throw. Needs to key off `acc_oauth_client_id` instead.
   - PKCE has to be turned on explicitly - `Jumbojett\OpenIDConnectClient::$codeChallengeMethod`
     defaults to `false` (`vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php:247`)
     and is never auto-negotiated from `code_challenge_methods_supported`; without calling
     `setCodeChallengeMethod('S256')` explicitly, the authorization request won't include a
     `code_challenge` and Fastmail's public-client flow will likely reject it. Needs a new
     extra-array marker (alongside the existing `ADD_CLIENT_TO_WELL_KNOWN`/`ADD_AUTH_PARAM`
     mechanism in `OpenIDConnectClient::$oauth_domain_regexps`), wired into **both**
     `OpenIDConnectClient::byDomain()` (`api/src/Auth/OpenIDConnectClient.php:128`) and
     `admin_mail::oauthToken()` (which builds its own `OpenIDConnectClient` instance directly,
     bypassing `byDomain()` - `admin/inc/class.admin_mail.inc.php:2630-2647`).
   - The library already handles the rest correctly once PKCE is on: `requestTokens()`
     (`vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php:954-960`) drops the empty
     `client_secret`/`Authorization: Basic` entirely when a code_verifier is present, and reads
     `token_endpoint_auth_methods_supported` from Fastmail's own discovery document, so it
     correctly skips Basic auth (`["none"]`, not `["client_secret_basic"]`) regardless.
3. **Register the real client** with `https://proxy.egroupware.org/oauth` as its redirect_uri
   (once Bron/manual registration can accept that, unlike dynamic registration) - no further
   redirect-URI design work needed, since that's already exactly how Google/Microsoft work today.
4. **Live-verify end-to-end** against ralf's real Fastmail account, same pattern as Milestone
   A.1's Stalwart verification: full wizard run, `Mail\Account::write()`, and actual mail
   send/receive.
5. Not investigated yet: whether Fastmail's classic-IMAP/SMTP OAuth scope
   (`urn:ietf:params:oauth:scope:mail`) is sufficient on its own, or whether the connection
   additionally needs `urn:ietf:params:oauth:scope:contacts`/`...:calendars` for anything - out of
   scope for the mail wizard specifically, worth a note if this project later extends to
   addressbook/calendar sync against Fastmail.
