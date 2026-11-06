# Removing `script-src 'unsafe-eval'` - Trusted Types, innerHTML enforcement, and jQuery

## Goal

Get rid of `script-src`'s long-standing `'unsafe-eval'` in `api/src/Header/ContentSecurityPolicy.php`
entirely, not just narrow it. `'unsafe-eval'` currently permits `eval()`/`new Function()` for *any*
string, from *any* caller, first-party or injected. Full removal needs Trusted Types *enforcement*
(`require-trusted-types-for 'script'`), which turns out to also gate every `innerHTML`/`outerHTML`/
`document.write()` assignment app-wide - which is where jQuery becomes the real blocker (see below).

## Status: Phase 1 done (master only), Phase 2 not started

### Phase 1 - narrow `'unsafe-eval'` to this app's own 7 call sites (done, 2026-10-05)

Committed/pushed master `d79988b426`. `api/js/jsapi/egw_trusted_types.ts` exports `trustedScript(code)`,
a thin wrapper around a Trusted Types policy (`egw-legacy-eval`), feature-detected with an identity
passthrough fallback for browsers without Trusted Types support. Routed through the 7 real
`new Function()` call sites found via a first-party `grep -rn "new Function("` sweep:

1. `api/js/etemplate/et2_core_legacyJSFunctions.ts` - legacy onchange/onclick attribute compiler
2. `api/js/etemplate/et2_widget_script.ts` - the `<script>` customisation widget
3. `api/js/etemplate/et2_extension_nextmatch_rowProvider.ts` - nextmatch's DOM-path compiler
4. `api/js/jsapi/egw_json.ts` - the JSON-response "script" plugin
5. `api/js/jsapi/egw_open.ts` - `open_link()`'s `javascript:` href handling
6-7. `notifications/js/app.ts` - two action-execution call sites

CSP's `script-src` now carries `'unsafe-eval'` AND `'trusted-types-eval'` side by side (deliberately
not replacing the former - a browser without Trusted Types support just falls back to `'unsafe-eval'`
with zero regression; one that enforces it blocks any un-wrapped `eval()` regardless of `'unsafe-eval'`
still nominally being present, since the two are independent gates that both must pass). Plus a
`trusted-types` policy-name allowlist: `egw-legacy-eval`, `lit-html`, `dompurify`, `'allow-duplicates'`
- the latter three needed because lit-html and DOMPurify each proactively create their own named
policy on page load, and this app's multi-bundle structure causes more than one copy of lit-html's own
`createPolicy('lit-html', ...)` bootstrap to run in the same document.

**`require-trusted-types-for 'script'` - the directive that actually enforces anything - is NOT
enabled.** Tried live 2026-10-05, reverted within the hour: see Phase 2 below for why.

Deliberately **master-only for now** (same reasoning as `expand-name-eval-removal` - see memory
`project_expand_name_eval_removal`) - wants this to sit and soak before considering a 26 backport,
given how foundational `et2_core_legacyJSFunctions.ts`'s own call site is.

### Phase 2 - enable real enforcement (`require-trusted-types-for 'script'`) - not started

This is the actual "remove `'unsafe-eval'`" step. Tried live 2026-10-05 and reverted within the hour:
it doesn't just gate `eval()`/`new Function()` - it enforces on **every** DOM-XSS sink app-wide
(`.innerHTML =`, `.outerHTML =`, `document.write()`, `insertAdjacentHTML()`, etc.), requiring a
`TrustedHTML` object instead of a plain string for all of them, everywhere, including third-party
code we don't control. Enabling it live immediately broke jQuery's own `.html()`/`innerHTML` calls and
the bundled `offline.min.js` library (`TypeError: Failed to set the 'innerHTML' property... This
document requires 'TrustedHTML' assignment`).

#### The innerHTML/outerHTML/document.write audit (2026-10-05)

First-party sweep (`grep -rn "\.innerHTML\s*=" --include="*.ts" .`, excluding tests/node_modules),
current count: **~27 sites across 16 files**. They split into two buckets:

- **Static/developer-controlled markup (~20 sites)** - calendar drag-n-drop time counters
  (`et2_widget_planner.ts`, `et2_widget_timegrid.ts`), AI-assistant button/typing-dots icons
  (`aiassistant/js/app.ts`), filterbox labels (`Et2Filterbox.ts`), invoker labels
  (`Et2InvokerMixin.ts`), kdots framework chrome (`EgwFrameworkMessage.ts`, `EgwFrameworkApp.ts`).
  Interpolate only formatted numbers/dates or fixed strings, nothing attacker-reachable. **Easy**:
  rewrite to `textContent`/`createElement()` (removes the sink entirely, no Trusted Types policy
  needed) or wrap with a dumb passthrough policy. Mechanical, a few hours total.
- **Genuinely attacker/user-influenceable content (~7 sites)** - `Et2File.ts` (uploaded filename
  interpolated raw into `innerHTML`), `notifications/js/app.ts:358` (notification message),
  `aiassistant/js/app.ts` (AI/tool output, several sites), `schulmanager/js/app.ts` (server row data),
  `admin/js/app.ts:539` (server-rendered container content), `egw_open.ts:696` (parsed popup
  response), `Et2Ai.ts:674` (template string). These are **pre-existing XSS exposure independent of
  CSP** - worth real sanitizing (DOMPurify, already bundled and used by `Et2Image`/`et2-html-area`)
  rather than a blind passthrough. This is the slower part: deciding what markup to actually allow per
  site and testing against hostile-looking input. Worth doing regardless of the CSP project.

Also found: `document.write()` at 2 sites in `mail/js/app.ts` (print-preview popups) and 1 in
`egw_json.ts` (`#wnd.document.write(res.data)`); `insertAdjacentHTML()` at `mail/js/app.ts`,
`api/js/jsapi/egw_tooltip.ts`. No `.outerHTML =` assignments found. Same two-bucket split applies.

**jQuery `.html()` call sites (first-party calls, not jQuery's internals): ~10**, mostly calendar
widgets (`et2_widget_event.ts`, `et2_widget_daycol.ts`, `et2_widget_planner.ts`,
`et2_widget_itempicker.ts`) - mostly static date/time labels, same easy content-wise, but harder
mechanically: the actual `innerHTML =` write happens **inside jQuery's own source**, not at our call
site, so we can't just wrap the string we pass in - jQuery itself would need to accept/produce a
`TrustedHTML` object.

#### jQuery and `offline.min.js` - not fixable at our call sites at all

`api/js/offline/offline.min.js` (bundled offline/online detection UI) and jQuery's own internals are
third-party source we don't control. The only way these (and the ~10 jQuery `.html()` call sites
above) keep working under enforcement is a specially-named **`'default'`** Trusted Types policy, which
the browser auto-invokes for *any* unwrapped sink write anywhere, first- or third-party.

**Important catch**: that `'default'` policy must implement **only `createHTML()`**, never
`createScript()` - if it defined `createScript()` too, it would auto-wrap *any* raw string passed to
`eval()`/`new Function()` from anywhere, silently undoing Phase 1's narrowing (defeats the whole point
of `egw-legacy-eval`).

A bare `'default'` `createHTML()` passthrough is cheap to add (~10 lines) and would unblock
enforcement mechanically, but gives near-zero real hardening against innerHTML-based XSS for the
third-party-covered sites. Doing it properly also means auditing/sanitizing the ~7 attacker-reachable
first-party sites above - that's the real work, maybe a day or two of careful per-site review.

#### jQuery version note

This codebase runs **jQuery 1.12.4** (`vendor/bower-asset/jquery`, pinned `^1.12.4` in
`composer.json`), from 2016. jQuery 4.0 (released 2025) is Trusted-Types-aware
(https://daily.dev/posts/jquery-4-0-0-javascript-library-features-trusted-types-ycv0lqp4p) and could
in principle make jQuery's own `.html()` calls Trusted-Types-compliant without the `'default'` policy
workaround - but 1.12.4 -> 4.0 is three major versions, dropping long-deprecated APIs
(`.bind()`/`.unbind()`/`.delegate()`, `jQuery.parseJSON`, various AJAX/deferred changes) that this
codebase still relies on (see `jquery-migrate.js` in `vendor/npm-asset/jquery/`, itself not
necessarily sufficient to bridge all the way to 4.0). **Not a near-term option** - a major-version
jQuery upgrade is its own substantial migration project, and conflicts with the existing direction of
moving *away* from jQuery rather than investing in it (see `feedback_no_new_jquery` memory and
`jquery-removal-inventory.md`).

## The real long-term fix: jQuery stops being loaded by default

Per `jquery-removal-inventory.md`, jQuery removal is **already the standing plan** for exactly the
apps/areas that matter here - Tier 1 in that inventory: `api/js/etemplate`'s et2 widget/framework core
(postponed, tracked in `jsapi-modernization.md`), `calendar`'s et2 widgets (the same
`et2_widget_event/daycol/planner/timegrid/itempicker.ts` files listed above as jQuery `.html()` call
sites), `smallpart`, `kanban`, and `projectmanager`'s gantt widget.

Today, jQuery is **not** a separate `<script>` - `api/js/jquery/jquery.noconflict.js` is statically
imported into the core `egw.min.js` bundle loaded on **every** page, specifically *because* Tier 1
(the et2 widget core) still depends on it synchronously on the normal render path. That's why a
`'default'` Trusted-Types catch-all currently has to cover jQuery at all: it's unconditionally present
everywhere.

**Once Tier 1 is migrated off jQuery** (the calendar widgets plus the et2 core itself), jQuery no
longer needs to load on every page - it becomes an opt-in compatibility layer for whatever legacy code
still imports it directly (if anything), not a blanket dependency of the core bundle. At that point:

- The `'default'` Trusted Types policy (or full enforcement generally) no longer needs to account for
  jQuery's own innerHTML internals at all - only whatever's actually still loaded on a given page.
- `jquery-removal-inventory.md`'s own "Future idea: lazy-load `jQuery` proxy" section already notes
  this precondition from the bundling angle ("only becomes worth pursuing once Tier 1 ... no longer
  depends on jQuery on the normal render path") - the same precondition applies here, from the CSP
  enforcement angle.
- `offline.min.js` would remain the one real third-party exception needing a narrow, explicit
  accommodation (or its own replacement/removal - not investigated here).

So Phase 2 of *this* project is effectively blocked on Tier 1 of the jQuery removal project, same as
the lazy-load-proxy idea is - two independent motivations converging on the same precondition. Until
Tier 1 is done, a `'default'` `createHTML()`-only policy is the pragmatic interim path if enforcement
is wanted sooner; once Tier 1 is done, enforcement can likely be enabled with a much smaller, fully
first-party-audited allowlist and no jQuery-shaped escape hatch needed at all.

## See also

- `jquery-removal-inventory.md` - the Tier 1/2/3/4 usage inventory and removal status this project's
  Phase 2 is blocked on.
- `jsapi-modernization.md` - why jQuery removal in `api/js/etemplate`/`api/js/jsapi` (the deepest part
  of Tier 1) was deliberately postponed.
- Memory `project_csp_unsafe_eval_removal_check` - the live-debugging session notes for Phase 1 (the
  3 live regressions hit and fixed while adding the `trusted-types` policy-name allowlist).
- Memory `feedback_no_new_jquery` - standing rule: don't introduce new jQuery usage.
