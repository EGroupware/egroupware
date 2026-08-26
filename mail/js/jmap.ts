/**
 * mail - direct client-side JMAP access for every mail account
 *
 * getRows()/refreshRows(), wired into NextMatch's row-fetch via fetchRows() +
 * egw.dataRegisterFetch() (see MailApp's constructor/destroy), are the only
 * way mail-list rows get fetched: the browser talks directly to a real JMAP
 * server (Stalwart) or to our own local IMAP-to-JMAP shim (mail/src/JmapShim.php,
 * for plain IMAP accounts) - there is no server-side row-fetch fallback.
 *
 * Label and custom-flag actions are handled directly with JMAP Email/set.
 * Other actions use Api\Mail::splitRowID() when they still need the legacy
 * PHP/IMAP path.
 *
 * @link: https://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import type {MailApp} from "./app";
import type {IegwAppLocal} from "../../api/js/jsapi/egw_global";
import JamClient from "jmap-jam";
import {JamWebSocketClient} from "./jmap-jam-websocket";
import type {StateChange} from "./jmap-jam-websocket";
import DOMPurify from "../../api/js/etemplate/Et2Image/dompurify-shim";
import {isNamespaceRootName, sortTopLevel} from "./folderTree";

interface JmapToken
{
	sessionUrl : string;
	accountId : string;
	access_token : string;
	expires_at : number;	// ms epoch, with our own safety-margin already subtracted
	isLocal : boolean;
	// true if the server has no working push-server (Api\Json\Push::onlyFallback()) - in that case,
	// and only in that case, MailJmap tries JamWebSocketClient's client-side onPush() instead of the
	// classic server-side JMAP push subscription (ProfileHandler::jmapBootstrap() calls that one
	// directly, server-side), since there's nothing working to lose: no regression risk even if a
	// given browser's WebSocket also doesn't connect.
	enableWsPush : boolean;
	// see ProfileHandler::THREADING_ENABLED's docblock (doc/ai/projects/mail-threaded-view.md,
	// Phase 1) - false for every account until that work ships; also currently doubles as the
	// "this account's backend doesn't support thread grouping yet" gate (Phase 1 is real-JMAP
	// only, Phase 2 will add IMAP THREAD support for local/shim accounts on top of this).
	supportsThreading : boolean;
	customLabels : Record<string, {name : string, color : string, icon? : string}>;
	trashFolder? : string;	// EGroupware "/"-joined folder path, e.g. "Trash" - see deleteMessages()
	junkFolder? : string;	// EGroupware "/"-joined folder path, e.g. "Junk" - null if not configured
	// Templates/Outbox have no IMAP SPECIAL-USE attribute or JMAP role at all - the account's own
	// configured (or default) name is the only way folderTree.ts's buildNode() can identify them
	// for a real JMAP server; see ProfileHandler::jmapBootstrap()'s docblock
	templatesFolder? : string;
	outboxFolder? : string;
}

export interface JmapMessageReference
{
	profileID : string;
	mailboxId : string;
	emailId : string;
}

/** Result of MailJmap.fetchBody() - see that method's docblock */
export type JmapBodyResult =
	{ special : true }
	|
	{
		special : false;
		html : string;
		attachments : any[];
		profileID : string;
		accountId : string;
		isLocal : boolean;
	};

export interface JmapGetRowsQuery
{
	selectedFolder : string;	// "profileID::folder/path"
	start? : number;
	num_rows? : number;
	sort? : string;				// 'ASC' | 'DESC'
	order? : string;			// column id, e.g. 'date', 'subject', 'fromaddress', ...
	cat_id? : string;			// search-type, see mail_ui::$searchTypes
	search? : string;
	filter? : string;			// status filter, see mail_ui::$statusTypes
	startdate? : string;
	enddate? : string;
	filter2? : string;			// truthy: "Sneak preview in list" toggle
	// truthy: group the top-level list by JMAP thread (doc/ai/projects/mail-threaded-view.md,
	// Phase 1). No caller sets this yet - see ProfileHandler::THREADING_ENABLED's docblock - and
	// getRows() ignores it unless the profile's token also reports supportsThreading.
	threaded? : boolean;
}

type EmailFilterCondition = Record<string, any>;
type EmailFilter = EmailFilterCondition | {operator : 'AND' | 'OR' | 'NOT', conditions : EmailFilter[]};

/**
 * Thrown by MailJmap methods when JMAP was actually reached and answered with a real error (an
 * RFC 8620 method-level ["error", {type, description}] response, or a Mailbox/set|Email/set
 * per-item SetError) - as opposed to a plain network/eligibility failure, which still just
 * resolves null/false so callers keep falling back to the classic ajax_* endpoint silently.
 * Callers that catch a JmapUserError should surface `.message` to the user (egw.message(),
 * a tree error-node, ...) rather than silently retrying via classic - the server has already
 * given a definitive answer, so retrying elsewhere would very likely fail the same way.
 */
export class JmapUserError extends Error {}

/**
 * Format one JMAP-shaped error object ({type, description?}) as a human string, or null if it
 * doesn't actually look like a JMAP/HTTP error object (eg. a plain fetch-failure Error/TypeError -
 * jmap-jam's own signal for "couldn't even talk to the server", left as the existing silent-
 * fallback path).
 */
function formatProblem(problem : any) : string | null
{
	if (!problem || typeof problem !== 'object' || typeof problem.type !== 'string') return null;
	return problem.description || problem.type;
}

/**
 * Classify a caught jmap-jam rejection: a real JMAP/HTTP error (single object or, from
 * requestMany(), an array of them) → human message; anything else (network failure, ineligible
 * account) → null, meaning "keep the existing silent-fallback behaviour".
 */
export function describeJmapError(e : any) : string | null
{
	if (Array.isArray(e))
	{
		const messages = e.map(formatProblem).filter((m) : m is string => m !== null);
		return messages.length ? messages.join('; ') : null;
	}
	return formatProblem(e);
}

/** Format one Mailbox/set or Email/set per-item SetError map (notCreated/notUpdated/notDestroyed), or null if absent/empty. */
export function describeSetError(setErrors : Record<string, any> | undefined) : string | null
{
	if (!setErrors || !Object.keys(setErrors).length) return null;
	return Object.values(setErrors).map(formatProblem).filter((m) : m is string => m !== null).join('; ') || null;
}

/**
 * egw.preference() for a checkbox-style preference returns the server's raw stored value, often
 * the literal string "0" for "off" - PHP's own !$value treats that as falsy, but a non-empty JS
 * string is ALWAYS truthy (only "", 0, null, undefined, NaN, false are falsy), so plain `!value`
 * silently inverts to the wrong answer for a "0"-stored preference. Bit us both in
 * MailJmap.getMailboxChildren()'s isSubscribed filter and MailApp.buildFolderLevelData()'s
 * client-side display filter - both used `!egw.preference(...)` directly.
 */
export function isPreferenceOn(value : any) : boolean
{
	return !!value && value !== '0';
}

/**
 * Direct JMAP access, using Stalwart or the local plain-IMAP JMAP shim selected by the bootstrap.
 */
export class MailJmap
{
	protected app : MailApp;

	// how long to skip re-checking a profile that just turned out not to be JMAP-eligible
	private static readonly INELIGIBLE_RECHECK_INTERVAL = 5 * 60 * 1000;

	// keyed by profileID, since a user may have several JMAP-backed mail accounts
	private tokens : Record<string, JmapToken> = {};
	private tokenPromises : Record<string, Promise<JmapToken | null>> = {};
	private clients : Record<string, JamWebSocketClient> = {};
	private refreshTimers : Record<string, number> = {};
	private ineligibleUntil : Record<string, number> = {};
	// whether enablePushOnce() has already (fire-and-forget) registered client-side WS push for the
	// profile's current token - reset whenever ensureToken() obtains a fresh token. Only relevant
	// when token.enableWsPush is true; the classic server-side path is handled entirely by
	// ProfileHandler::jmapBootstrap() and needs no client-side bookkeeping at all.
	private pushEnabled : Record<string, boolean> = {};
	// "profileID::folder/path" -> JMAP Mailbox id
	private mailboxIds : Record<string, string> = {};
	// profileID -> JMAP folderId -> path ("INBOX", "INBOX/Sub", ...) - see folderId2path()
	private folderPaths : Record<string, Record<string, string>> = {};
	// profileID -> JMAP accountId -> dataType ("Email"/"Mailbox") -> last-seen JMAP state string -
	// the baseline enableWsPush()'s onPush() callback diffs each new StateChange against, see
	// processWsPushStates()
	private wsPushStates : Record<string, Record<string, Record<string, string>>> = {};
	private static readonly CUSTOM_FLAGS = ['customFlag1', 'customFlag2', 'customFlag3', 'customFlag4', 'customFlag5'];
	// standard (non-label, non-custom-flag) system flags the UI can bulk-toggle for an explicit
	// selection - keyed by the base ("un"-stripped) action id used throughout mail/js/app.ts
	private static readonly SYSTEM_FLAG_KEYWORDS : Record<string, string> = {read: '$seen', flagged: '$flagged'};
	private static readonly QUERY_PAGE_SIZE = 500;
	// RFC 8621 §4.1.3 header-property name for the MDN (read-receipt) prompt - matches
	// JmapShim::MDN_HEADER_PROPERTY (mail/src/JmapShim.php), which echoes this same key back for
	// local-shim accounts; a real JMAP server (Stalwart) does so natively per spec
	private static readonly MDN_HEADER_PROPERTY = 'header:disposition-notification-to:asText';
	// JMAP Quota extension (RFC 9425) - matches Mail\Jmap::JMAP_QUOTA (api/src/Mail/Jmap.php)
	private static readonly JMAP_QUOTA = 'urn:ietf:params:jmap:quota';
	// Per-profile cache of getQuota()'s formatted result - refreshQuotaDisplay() (app.ts)
	// is called on every single folder click, not just an actual account switch, and quota
	// rarely changes visibly between those - a long TTL avoids a fresh (comparatively expensive)
	// JMAP round-trip (or worse, falling back to the classic IMAP connect+examine chain) on
	// every one of those.
	private quotaCache : Record<string, { data : Record<string, any>, expires : number }> = {};
	private static readonly QUOTA_CACHE_TTL = 2 * 60 * 60 * 1000;

	private static readonly CSP_RELOAD_KEY = 'mail_jmap_csp_reload';
	private static cspListenerInstalled = false;
	// the most-recently-constructed instance - the securitypolicyviolation listener below is
	// installed (window-level) only once, but needs the CURRENT instance's this.tokens to decide
	// whether a violation is actually one of our own accounts, so it's looked up through here
	// rather than closing over "this" from whichever instance happened to install the listener
	private static current : MailJmap | null = null;

	get egw() : IegwAppLocal
	{
		return this.app.egw;
	}

	constructor(app : MailApp)
	{
		this.app = app;
		MailJmap.current = this;
		if (!MailJmap.cspListenerInstalled)
		{
			MailJmap.cspListenerInstalled = true;
			window.addEventListener('securitypolicyviolation',
				(e : SecurityPolicyViolationEvent) => MailJmap.current?.onCspViolation(e));
		}
	}

	/**
	 * Recover from a page that was loaded BEFORE this account's JMAP host was added to our own
	 * Content-Security-Policy's connect-src allowlist (mail_hooks::csp_connect_src()) - a brand
	 * new Stalwart account (real external host, not one of the same-origin sentinel values) is
	 * only in that hook's answer once it exists in the DB, but the CURRENT page's CSP response
	 * header was already sent before that; the browser then blocks this account's own session
	 * fetch and reports it via this `securitypolicyviolation` event.
	 *
	 * Reacting to the EVENT directly (rather than to the resulting fetch/session promise
	 * rejection, which was this method's first version) matters: the two are not ordered relative
	 * to each other (the violation event is a separately browser-task-queued report, not
	 * necessarily dispatched before the fetch's own promise settles) - checking from a
	 * `client.session.catch()` handler could run before this event fired at all, silently missing
	 * every violation. Confirmed live 2026-08-24: cspBlockedOrigins DID end up containing the
	 * blocked origin, but the promise-based check that used to consult it had already run and
	 * found it empty, so no reload was ever triggered.
	 *
	 * A stale CSP fixes itself with a single full window reload (the very next page load
	 * regenerates the header from current account data) - so that is exactly what this does, at
	 * most ONCE per browser tab (guarded via sessionStorage, survives the reload) to avoid ever
	 * reload-looping over an account that is genuinely unreachable for some OTHER reason, and
	 * only for a blocked host that matches one of THIS user's own mail accounts (never for some
	 * unrelated connect-src violation, eg. from a browser extension sharing the page). Matching
	 * on HOSTNAME alone, not the full origin: the browser's WebSocket push upgrade
	 * (JamWebSocketClient) connects to the same host via `wss://`/`ws://`, a different scheme
	 * than the account's own `https://`/`http://` sessionUrl, so a scheme-inclusive origin
	 * comparison would miss that violation (found live 2026-08-24, alongside adding the matching
	 * ws(s):// connect-src entries in mail_hooks::csp_connect_src()).
	 */
	private onCspViolation(e : SecurityPolicyViolationEvent) : void
	{
		if (e.violatedDirective?.split(' ')[0] !== 'connect-src' || !e.blockedURI) return;
		let host : string;
		try { host = new URL(e.blockedURI).hostname; } catch (_e) { return; }
		const isOwnAccount = Object.values(this.tokens).some((token) =>
		{
			try { return new URL(token.sessionUrl).hostname === host; } catch (_e) { return false; }
		});
		if (!isOwnAccount || sessionStorage.getItem(MailJmap.CSP_RELOAD_KEY)) return;
		sessionStorage.setItem(MailJmap.CSP_RELOAD_KEY, '1');
		window.location.reload();
	}

	destroy()
	{
		Object.values(this.refreshTimers).forEach(timer => window.clearTimeout(timer));
		this.refreshTimers = {};
		this.app = null;
	}

	/**
	 * Fetch mail-list rows directly from the JMAP server (or the local IMAP shim)
	 *
	 * Never throws for "this account isn't reachable right now" - returns null in that case,
	 * so fetchRows() can surface it as a fetch failure instead of an unhandled rejection.
	 *
	 * @return null if this account has no usable JMAP access-token (server unreachable, MFA, ...)
	 */
	async getRows(query : JmapGetRowsQuery) : Promise<{ rows : any[], total : number } | null>
	{
		const [profileID, folder] = (query.selectedFolder || '').split('::', 2);
		if (!profileID || !folder)
		{
			throw new Error("MailJmap.getRows(): query.selectedFolder must be 'profileID::folder/path'");
		}

		const token = await this.ensureToken(profileID);
		if (!token)
		{
			return null;
		}
		// doc/ai/projects/mail-threaded-view.md, Phase 1 UI toggle - cheap (cached boolean, no
		// extra round trip), self-corrects on every fetch so switching to/from a
		// (Phase 2+, not yet real) non-supporting profile always resolves to the right state
		this.app.updateThreadingToggle?.(!!token.supportsThreading);
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);

		const start = query.start || 0;
		const limit = query.num_rows || 50;
		// matches mail_ui::get_rows()'s "fetchPreview" behaviour: the (comparatively expensive)
		// message-body preview snippet is only fetched when the "Sneak preview in list" toggle
		// (filter2 / mail.ShowDetails preference) is on
		const fetchPreview = !!query.filter2;

		// doc/ai/projects/mail-threaded-view.md, Phase 1 - only reachable once
		// ProfileHandler::THREADING_ENABLED is flipped true (nothing sets query.threaded yet, and
		// token.supportsThreading is false for every account until then either way), so this is
		// dead code in production for now, not a behaviour change.
		if (query.threaded && token.supportsThreading)
		{
			return this.getThreadedRows(client, token, profileID, mailboxId, query, start, limit, fetchPreview);
		}

		const [{ids, emails}] = await client.requestMany((t) =>
		{
			const ids = t.Email.query({
				accountId: token.accountId,
				filter: this.buildFilter(query, mailboxId),
				sort: this.buildSort(query),
				position: start,
				limit,
				calculateTotal: true,
			});
			const properties = [
				'id', 'keywords', 'size', 'receivedAt', 'sentAt', 'subject',
				'from', 'to', 'cc', 'bcc', 'hasAttachment', MailJmap.MDN_HEADER_PROPERTY,
			];
			if (fetchPreview)
			{
				properties.push('preview');
			}
			const emails = t.Email.get({
				accountId: token.accountId,
				ids: ids.$ref('/ids'),
				properties,
			});
			return {ids, emails};
		});

		return {
			rows: (emails.list || []).map((email : any) => this.email2row(email, profileID, mailboxId)),
			total: ids.total ?? (emails.list || []).length,
		};
	}

	/**
	 * Top-level row list for a "group by thread" fetch (doc/ai/projects/mail-threaded-view.md,
	 * Phase 1) - collapses the folder's messages into one representative Email per JMAP thread
	 * (RFC 8621 Email/query's collapseThreads), then fetches every thread's member ids
	 * (Thread/get) plus their keywords (Email/get) in one further chained JMAP request, purely to
	 * compute the closed-row aggregate (see aggregateThreadKeywords()) - a single-message thread
	 * skips all of that and renders via the ordinary email2row(), so the common case (most threads
	 * in most inboxes) costs nothing extra over the flat list.
	 *
	 * Expanding a resulting thread-parent row is a separate fetch, handled by fetchRows()'s
	 * `_queriedRange.parent_id` branch (getThreadMemberRows()) - RFC 8621 threads are flat, so
	 * there is never a second level to expand.
	 */
	private async getThreadedRows(client : JamClient, token : JmapToken, profileID : string, mailboxId : string,
		query : JmapGetRowsQuery, start : number, limit : number, fetchPreview : boolean) : Promise<{ rows : any[], total : number }>
	{
		const properties = [
			'id', 'threadId', 'keywords', 'size', 'receivedAt', 'sentAt', 'subject',
			'from', 'to', 'cc', 'bcc', 'hasAttachment', MailJmap.MDN_HEADER_PROPERTY,
		];
		if (fetchPreview)
		{
			properties.push('preview');
		}
		const [{ids, emails}] = await client.requestMany((t) =>
		{
			const ids = t.Email.query({
				accountId: token.accountId,
				filter: this.buildFilter(query, mailboxId),
				sort: this.buildSort(query),
				position: start,
				limit,
				calculateTotal: true,
				collapseThreads: true,
			});
			const emails = t.Email.get({
				accountId: token.accountId,
				ids: ids.$ref('/ids'),
				properties,
			});
			return {ids, emails};
		});

		const representatives = emails.list || [];
		const total = ids.total ?? representatives.length;
		const threadIds = Array.from(new Set(representatives.map((e : any) => e.threadId).filter(Boolean)));
		if (!threadIds.length)
		{
			return {rows: representatives.map((e : any) => this.email2row(e, profileID, mailboxId)), total};
		}

		// Thread/get -> Email/get(keywords only) for every thread on this page, chained in one
		// request via the '/list/*/emailIds' wildcard result-reference (live-verified against
		// Stalwart, see doc/ai/projects/mail-threaded-view.md) - cheap, since only 'keywords' is
		// requested for members other than the representative already fetched above.
		//
		// Both invocations MUST be properties of the returned object, not just 'members' - jmap-jam
		// only actually sends an invocation as part of the outgoing batch if it's a property of the
		// callback's return value, even though 'threads' is only ever used here via .$ref(), never
		// read directly. Omitting it silently drops Thread/get from the request entirely, leaving
		// Email/get's '#ids' pointing at a call that was never sent - live-reproduced while testing
		// the UI toggle: requestMany() hung indefinitely rather than erroring (see
		// doc/ai/projects/mail-threaded-view.md's "Live Stalwart verification" section for the
		// unrelated, already-documented sibling instance of this exact jmap-jam contract).
		const [{threads, members}] = await client.requestMany((t) =>
		{
			const threadArgs : any = {accountId: token.accountId, ids: threadIds};
			if (token.isLocal)
			{
				// JmapShim's ids are plain per-mailbox IMAP UIDs, not globally-unique real-JMAP
				// ids - Thread/get needs this local-only extension to know which mailbox to
				// search (see JmapShim::threadGet()); real JMAP (Stalwart) never receives it
				threadArgs.mailboxId = mailboxId;
			}
			const threads = t.Thread.get(threadArgs);
			const members = t.Email.get({
				accountId: token.accountId,
				ids: threads.$ref('/list/*/emailIds'),
				properties: ['id', 'threadId', 'keywords'],
			});
			return {threads, members};
		});

		const membersByThread = new Map<string, { id : string, keywords? : Record<string, boolean> }[]>();
		(members.list || []).forEach((m : any) =>
		{
			if (!membersByThread.has(m.threadId))
			{
				membersByThread.set(m.threadId, []);
			}
			membersByThread.get(m.threadId)!.push(m);
		});

		const rows = representatives.map((email : any) =>
		{
			const threadMembers = membersByThread.get(email.threadId);
			return !threadMembers || threadMembers.length <= 1
				? this.email2row(email, profileID, mailboxId)
				: this.emails2threadRow(email, threadMembers, email.threadId, profileID, mailboxId);
		});

		return {rows, total};
	}

	/**
	 * Child rows for one expanded thread-parent row (doc/ai/projects/mail-threaded-view.md, Phase
	 * 1) - called from fetchRows()'s `_queriedRange.parent_id` branch. Every member renders via
	 * the ordinary email2row(), so an expanded thread's messages look and behave exactly like
	 * normal list rows.
	 *
	 * Order is Thread/get's own (RFC 8621: "ordered by date", ascending, oldest first) - matches
	 * how most mail clients lay out an open conversation top-to-bottom; not tied to whatever sort
	 * order the (hidden, per `.noVisibleHeader` on the sub-grid) parent list is using.
	 */
	private async getThreadMemberRows(threadId : string, selectedFolder : string, fetchPreview : boolean) :
		Promise<{ rows : any[], total : number } | null>
	{
		const [profileID, folder] = selectedFolder.split('::', 2);
		const token = await this.ensureToken(profileID);
		if (!token || !token.supportsThreading)
		{
			// a thread-parent row can't exist without supportsThreading, so this is only reachable
			// if the account/token changed mid-session - answer empty, not an error
			return {rows: [], total: 0};
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const properties = [
			'id', 'keywords', 'size', 'receivedAt', 'sentAt', 'subject',
			'from', 'to', 'cc', 'bcc', 'hasAttachment', MailJmap.MDN_HEADER_PROPERTY,
		];
		if (fetchPreview)
		{
			properties.push('preview');
		}
		// both invocations must be properties of the returned object - see getThreadedRows()'s
		// identical comment on the same jmap-jam requestMany() contract
		const [{thread, emails}] = await client.requestMany((t) =>
		{
			const threadArgs : any = {accountId: token.accountId, ids: [threadId]};
			if (token.isLocal)
			{
				// see getThreadedRows()'s identical comment
				threadArgs.mailboxId = mailboxId;
			}
			const thread = t.Thread.get(threadArgs);
			const emails = t.Email.get({
				accountId: token.accountId,
				ids: thread.$ref('/list/*/emailIds'),
				properties,
			});
			return {thread, emails};
		});

		const rows = (emails.list || []).map((email : any) => this.email2row(email, profileID, mailboxId));
		return {rows, total: rows.length};
	}

	/**
	 * Expand any thread-parent row ids (emails2threadRow()) in a bulk-action selection into their
	 * real member message row ids - every other id passes through unchanged, and the whole call is
	 * a same-tick no-op (no network) when nothing in the selection is a thread row, i.e. always
	 * today, while ProfileHandler::THREADING_ENABLED is false.
	 *
	 * Called by app.ts just before turning a selection into JMAP message references
	 * (messageReference()), so move/delete/flag/etc. always operate on real messages, never a
	 * synthetic "thread:<id>" uid a JMAP server would reject. Ralf's decision (doc/ai/projects/
	 * mail-threaded-view.md, "Bulk actions on collapsed thread rows"): a bulk action on a thread
	 * row applies to every one of its member messages, same as if they'd all been selected
	 * individually - this is the one place that expansion actually happens; app.ts's own
	 * confirmation-count logic (expandedSelectionCount()) mirrors the *counting* half of the same
	 * decision without needing this round trip, since thread_count is already cached on the row.
	 */
	async expandThreadRowIds(ids : string[]) : Promise<string[]>
	{
		const expandable = ids
			.map((id) => ({id, threadId: this.threadIdOf(id)}))
			.filter((entry) : entry is { id : string, threadId : string } => !!entry.threadId);
		if (!expandable.length)
		{
			return ids;
		}
		const expansions = await Promise.all(expandable.map(async({id, threadId}) =>
			({id, memberIds: await this.threadMemberRowIds(id, threadId)})));
		const memberIdsById = new Map(expansions.map(({id, memberIds}) => [id, memberIds]));
		return ids.flatMap((id) => memberIdsById.get(id) ?? [id]);
	}

	/** A row's thread id (emails2threadRow()), or null if it isn't a thread-parent row at all. */
	private threadIdOf(rowId : string) : string | null
	{
		const data = this.egw.dataGetUIDdata(rowId)?.data;
		return data?.is_parent && data?.thread_id ? data.thread_id : null;
	}

	/**
	 * Real member row ids for one thread-parent row - profileID/mailboxId come straight out of the
	 * thread row's own row_id (accountId::profileID::mailboxId::thread:<id>, see
	 * emails2threadRow()), no folder-path lookup needed (unlike getThreadMemberRows(), used for the
	 * sub-grid expand-on-click fetch instead, which only has a folder path to start from).
	 *
	 * Falls back to returning the thread row's own (synthetic) id unchanged on any failure - the
	 * caller's existing messageReference()/handleJmapError() machinery already knows how to surface
	 * or fall back on a row id it can't use, so this doesn't need its own separate error handling.
	 */
	private async threadMemberRowIds(threadRowId : string, threadId : string) : Promise<string[]>
	{
		const parts = threadRowId.split('::');
		const profileID = parts[1];
		const mailboxId = parts[2];
		const token = await this.ensureToken(profileID);
		const client = this.clients[profileID];
		if (!token || !client)
		{
			return [threadRowId];
		}
		try
		{
			// both invocations must be properties of the returned object - see getThreadedRows()'s
			// identical comment on the same jmap-jam requestMany() contract
			const [{thread, emails}] = await client.requestMany((t) =>
			{
				const threadArgs : any = {accountId: token.accountId, ids: [threadId]};
				if (token.isLocal)
				{
					// see getThreadedRows()'s identical comment
					threadArgs.mailboxId = mailboxId;
				}
				const thread = t.Thread.get(threadArgs);
				const emails = t.Email.get({
					accountId: token.accountId,
					ids: thread.$ref('/list/*/emailIds'),
					properties: ['id'],
				});
				return {thread, emails};
			});
			return (emails.list || []).map((email : any) =>
				this.app.egw.user('account_id') + '::' + profileID + '::' + mailboxId + '::' + email.id);
		}
		catch (e)
		{
			console.error('MailJmap.threadMemberRowIds(): failed to expand a thread row, leaving it as-is', e);
			return [threadRowId];
		}
	}

	/**
	 * Get formatted quota-display data for $profileID directly via JMAP, avoiding the classic
	 * IMAP connect+examine round-trip mail_ui::ajax_refreshQuotaDisplay() would otherwise need
	 * (that path was the source of a real production hang/error against a flaky IMAP backend).
	 * Result shape matches what MailApp.setQuotaDisplay() (app.ts) expects, so a caller can
	 * hand it straight through with no PHP round-trip at all when this resolves non-null.
	 *
	 * Briefly cached (see quotaCache): refreshQuotaDisplay() is called on every folder
	 * click, not just an actual account switch.
	 *
	 * @return null only when a genuinely different code path is worth trying: it's a real JMAP
	 *  server (Stalwart, token.isLocal false) that doesn't advertise the Quota extension
	 *  (RFC 9425) - caller falls back to the classic ajax_refreshQuotaDisplay() round-trip (real
	 *  IMAP protocol against the same server) in that case, a genuinely different capability, not
	 *  a broken/unreachable connection. Never null for a local/shim account (token.isLocal true):
	 *  JmapShim implements Quota/get by wrapping the exact same classic IMAP QUOTA lookup
	 *  ajax_refreshQuotaDisplay() would otherwise fall back to, so declining there would only
	 *  repeat an identical lookup for no benefit - answers "not supported" directly instead
	 *  (mirrors the classic path's own "$quota===false" display). Likewise, no usable token at all
	 *  (account unreachable) answers "not reachable" directly rather than resolving null - retrying
	 *  the identical IMAP connection classically would just hit the same failure (see
	 *  folderTreeAutoload()'s docblock in app.ts for why there's no classic fallback for that).
	 */
	async getQuota(profileID : string) : Promise<Record<string, any> | null>
	{
		const cached = this.quotaCache[profileID];
		if (cached && cached.expires > Date.now())
		{
			return cached.data;
		}
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			return this.unavailableQuotaDisplay(profileID, this.egw.lang('Account not reachable'));
		}
		const client = this.clients[profileID];
		const session = await client.session;
		let quota : any = null;
		if (session.capabilities?.[MailJmap.JMAP_QUOTA])
		{
			// jmap-jam's fluent t.Entity.method() builder only knows the handful of entities it
			// ships types for (Email, Mailbox, ...) - Quota (RFC 9425) isn't one of them, so this
			// uses the lower-level request() escape hatch instead, same as Mail\Jmap's own raw
			// jmapCall() array form server-side (api/src/Mail/Jmap.php's getQuota())
			const [{list}] : any = await (client.request as any)(['Quota/get', {
				accountId: token.accountId,
				ids: null,
			}], {using: [MailJmap.JMAP_QUOTA]});
			quota = (list || []).find((q : any) => q.resourceType === 'octets' && (q.scope ?? 'account') === 'account');
		}
		else if (!token.isLocal)
		{
			return null;
		}
		const data = quota
			? this.formatQuotaDisplay(Math.round(quota.used / 1024), Math.round(quota.hardLimit / 1024), profileID)
			: (token.isLocal ? this.unavailableQuotaDisplay(profileID, this.egw.lang('Quota not provided by server')) : null);
		if (data)
		{
			this.quotaCache[profileID] = {data, expires: Date.now() + MailJmap.QUOTA_CACHE_TTL};
		}
		return data;
	}

	/**
	 * Invalidate the cached quota for $profileID (see getQuota()'s quotaCache) - call after an
	 * action that can actually change usage (emptying trash/junk, deleting a whole folder), so
	 * the next refreshQuotaDisplay() call (app.ts) fetches a fresh value instead of serving
	 * the last (up to QUOTA_CACHE_TTL old) cached one.
	 */
	invalidateQuota(profileID : string) : void
	{
		delete this.quotaCache[profileID];
	}

	/**
	 * "Quota not available" display data (hidden via quotaclass, same as mail_ui::
	 * ajax_refreshQuotaDisplay()'s own "$quota===false" branch) - used both for a real JMAP server
	 * not advertising the Quota extension and for an unreachable account, distinguished only by
	 * $message.
	 */
	private unavailableQuotaDisplay(profileID : string, message : string) : Record<string, any>
	{
		return {
			data: {
				quota: message,
				quotainpercent: '0',
				quotaclass: 'mail_DisplayNone',
				profileid: profileID,
				quotafreespace: '',
			},
			quotawarning: false,
		};
	}

	/**
	 * Format usage/limit (both in KB, matching Api\Mail::getQuotaRoot()'s units) into the shape
	 * MailApp.setQuotaDisplay() (app.ts) expects - client-side port of
	 * ProfileHandler::quotaDisplay() + mail_ui::ajax_refreshQuotaDisplay()'s own content-building
	 * (mail/src/Ui/ProfileHandler.php, mail/inc/class.mail_ui.inc.php), so a JMAP-direct quota
	 * fetch never needs the server round-trip just to format the numbers. Unlike the classic
	 * path, "quotawarning" is returned at the top level (not nested under "data") to match what
	 * setQuotaDisplay() actually reads.
	 */
	private formatQuotaDisplay(usageKB : number, limitKB : number, profileID : string) : Record<string, any>
	{
		const percent = limitKB === 0 ? 100 : Math.round(usageKB * 100 / limitKB);
		const usageText = MailJmap.showReadableSize(usageKB * 1024);
		const text = limitKB > 0 ? `${usageText}/${MailJmap.showReadableSize(limitKB * 1024)}` : usageText;
		const cls = limitKB > 0
			? (percent > 90 ? 'mail-index_QuotaRed' : percent > 80 ? 'mail-index_QuotaYellow' : 'mail-index_QuotaGreen')
			: 'mail-index_QuotaGreen';
		const freespace = limitKB * 1024 - usageKB * 1024;
		const quotaLimitWarning = Number(this.egw.config('quota_limit_warning', 'mail')) || 30;

		return {
			data: {
				quota: this.egw.lang('Quota: %1', text),
				quotainpercent: String(percent),
				quotaclass: cls,
				profileid: profileID,
				quotafreespace: MailJmap.showReadableSize(freespace),
			},
			quotawarning: Math.ceil(freespace / (1024 * 1024)) < quotaLimitWarning,
		};
	}

	/**
	 * Client-side port of Api\Mail::show_readable_size() (api/src/Mail.php) - must stay in sync
	 * with the exact same (slightly unusual: MB is truncated to one decimal BEFORE the GB
	 * conversion) rounding behaviour, so quota text looks identical regardless of whether it came
	 * from the classic PHP path or this direct-JMAP one.
	 */
	private static showReadableSize(bytes : number) : string
	{
		bytes /= 1024;
		let type = 'k';
		if (bytes / 1024 > 1)
		{
			bytes /= 1024;
			type = 'M';
			if (bytes / 1024 > 1)
			{
				bytes = Math.trunc(bytes * 10) / 10;
				bytes /= 1024;
				type = 'G';
			}
		}
		bytes = bytes < 10 ? Math.trunc(bytes * 10) / 10 : Math.trunc(bytes);
		return bytes + ' ' + type;
	}

	/**
	 * Fetch one level's worth of a mailbox's direct children - lazy per-level folder-tree
	 * loading (see doc/ai/projects/mail-folder-tree-jmap.md). Batches Mailbox/query (list
	 * children of parentId) + Mailbox/get (full node data for those ids) in one request, the
	 * same result-reference pattern getRows() already uses for Email/query+Email/get.
	 *
	 * Never throws for "this account isn't reachable right now" - returns null so the caller
	 * (MailApp.folderTreeAutoload(), mail/js/app.ts) can show an error leaf for that one
	 * node instead of silently retrying via a second, classic code path.
	 *
	 * @param profileID
	 * @param parentId JMAP Mailbox id of the parent, or null for the top level
	 * @param isTopLevel true for the account root (parentId===null) or INBOX's own direct
	 *  children - classic mail_tree.inc.php only ever special-cases folder names/roles at this
	 *  same "top level" scope (Api\Mail::getFolderArrays()'s $_onlyTopLevel mode); any deeper
	 *  level always uses plain generic icons and the raw name, even for a folder that happens to
	 *  carry a matching name/role - see the templates/outbox name-matching below
	 * @param subscribedOnly explicit override - omit to fall back to the showAllFoldersInFolderPane
	 *  preference (the main browsing tree's own behaviour). The folder-management dialog always
	 *  passes `false` here regardless of that preference, matching classic mail_tree.inc.php's own
	 *  folderManagement()/ajax_folderMgmtTree_autoloading() calls (which hardcoded
	 *  $_subscribedOnly=false): that dialog manages folders, including unsubscribed ones, so it
	 *  must never hide any of them.
	 * @return null if this account has no usable JMAP access-token (server unreachable, MFA, ...)
	 */
	async getMailboxChildren(profileID : string, parentId : string | null, isTopLevel : boolean,
		subscribedOnly? : boolean) : Promise<any[] | null>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token)
			{
				return null;
			}
			const client = this.clients[profileID];
			// matches classic mail_ui's own default (mail_tree.inc.php's getInitialIndexTree()
			// call: $_subscribedOnly = !showAllFoldersInFolderPane) - without this, JmapShim's
			// underlying IMAP LIST mode returns literally every mailbox regardless of
			// subscription, flooding the tree with stale/unsubscribed folders classic never
			// showed by default; a real JMAP server filters the same way via RFC 8621's standard
			// isSubscribed MailboxFilterCondition
			const filter : Record<string, any> = {parentId};
			const effectiveSubscribedOnly = subscribedOnly ?? !isPreferenceOn(this.egw.preference('showAllFoldersInFolderPane', 'mail'));
			if (effectiveSubscribedOnly)
			{
				filter.isSubscribed = true;
			}

			const [{mailboxes}] = await client.requestMany((t) =>
			{
				const ids = t.Mailbox.query({
					accountId: token.accountId,
					filter,
				});
				const mailboxes = t.Mailbox.get({
					accountId: token.accountId,
					ids: ids.$ref('/ids'),
				});
				// both invocations must be in the returned object - jmap-jam only assigns a
				// resolvable callId (and therefore only actually sends) an invocation that's a key
				// of this return value, so omitting "ids" here left the query out of the batch
				// entirely and broke the result-reference ("missing field `resultOf`" from a real
				// JMAP server, "Failed to resolve result reference" from JmapShim's local one)
				return {ids, mailboxes};
			});
			const list = mailboxes.list || [];
			if (isTopLevel)
			{
				this.applyTemplateOutboxRoles(list, token);
			}
			// sort role-tagged siblings into their fixed order (see folderTree.ts's sortTopLevel())
			// whenever this level actually has any - not gated on isTopLevel: a shared/other-user
			// mailbox's own special-folder set (eg. a folder nested under "Shared Folders") still
			// gets a real role from the server at any depth, and deserves the same ordering as the
			// account's own top level. A level of ordinary personal subfolders has no role-tagged
			// entries at all, so this is a no-op for them, unlike applyTemplateOutboxRoles() above
			// (an account-specific name-match that must stay isTopLevel-only).
			if (isTopLevel || list.some((m : any) => !!m.role))
			{
				sortTopLevel(list);
			}
			if (!token.isLocal)
			{
				await this.resolveHasChildren(client, token.accountId, list, effectiveSubscribedOnly);
				if (parentId === null)
				{
					await this.resolveAclCapable(client, token, list);
				}
			}
			return list;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.getMailboxChildren(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.getMailboxChildren(): failed, caller shows an error leaf', e);
			return null;
		}
	}

	/**
	 * Templates/Outbox have no IMAP SPECIAL-USE attribute or JMAP role at all - JmapShim already
	 * resolves them server-side via acc_folder_template/acc_folder_outbox for the local shim, but
	 * a real JMAP server (Stalwart) has no equivalent mechanism, so match by the account's own
	 * configured (or default) folder name here instead, before this shape reaches folderTree.ts's
	 * buildNode() - case-insensitive, matching classic roleFor()'s own strcasecmp() convention.
	 * Only ever called for a top-level list (see getMailboxChildren()'s own isTopLevel docblock) -
	 * classic never applies this at any deeper level either. Mutates `list` in place.
	 */
	private applyTemplateOutboxRoles(list : any[], token : JmapToken) : void
	{
		list.forEach((m : any) =>
		{
			if (m.role) return;
			const name = (m.name || '').toLowerCase();
			if (token.templatesFolder && name === token.templatesFolder.toLowerCase()) m.role = 'templates';
			else if (token.outboxFolder && name === token.outboxFolder.toLowerCase()) m.role = 'outbox';
		});
	}

	/**
	 * Combined single-pass fetch of the account root level AND INBOX's own direct children -
	 * INBOX is always auto-expanded on initial render (see folderTree.ts's buildNode(), "open:
	 * true" for role 'inbox'), so without this, Et2Tree would immediately fire its own separate,
	 * purely reactive lazy-load request for INBOX right after the root level renders (ralf's
	 * observation: this always shows up as an extra "2nd request" in the network tab, on every
	 * single page load). MailApp.buildRootFolderData() (app.ts) embeds inboxChildren directly
	 * into the root INBOX node's `item` before ever handing the array to Et2Tree, so INBOX's own
	 * `lazy` flag (Et2Tree.ts's _optionTemplate()) reads false and it never dispatches that
	 * lazy-load event at all - not just "faster", the second request is eliminated outright.
	 *
	 * Still two HTTP requests, not one: RFC 8620 result references can only substitute a whole
	 * top-level method argument, not a single field nested inside another argument (`filter.
	 * parentId` here), so "list the children of whatever mailbox has role=inbox" can't be
	 * expressed as a single chained JMAP call - the id has to come back from the server before a
	 * query for its children can even be built. What this DOES do is (a) fold the "find INBOX's
	 * id" lookup into the very same request as the root-level fetch (it's independent of that
	 * fetch, so it rides along for free instead of needing its own separate round trip the way
	 * mailboxId('INBOX') normally would), and (b) fire the children request immediately once that
	 * resolves, in this one code path - instead of only finding out INBOX needs its children once
	 * Et2Tree has already rendered the root level, noticed the (still-empty) INBOX node is marked
	 * open, and reactively dispatched a lazy-load event for it (a full Lit render + updateComplete
	 * + DOM CustomEvent round trip, on top of the request that event then triggers).
	 *
	 * @param subscribedOnly explicit override - omit to fall back to the showAllFoldersInFolderPane
	 *  preference, same meaning and default as getMailboxChildren()'s own param (see its docblock)
	 * @return null if this account has no usable JMAP access-token (same contract as getMailboxChildren())
	 */
	async getRootFolders(profileID : string, subscribedOnly? : boolean) : Promise<{ top : any[], inboxChildren : any[] | null } | null>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return null;
			const client = this.clients[profileID];
			const filter : Record<string, any> = {parentId: null};
			const effectiveSubscribedOnly = subscribedOnly ?? !isPreferenceOn(this.egw.preference('showAllFoldersInFolderPane', 'mail'));
			if (effectiveSubscribedOnly)
			{
				filter.isSubscribed = true;
			}

			const [{topMailboxes, inboxIds}] = await client.requestMany((t) =>
			{
				const topIds = t.Mailbox.query({accountId: token.accountId, filter});
				const topMailboxes = t.Mailbox.get({accountId: token.accountId, ids: topIds.$ref('/ids')});
				// same plain name lookup mailboxId() already uses to resolve any single path
				// segment - not role-based: this is the one mechanism already proven correct for
				// both backends (getRows()' own folder resolution goes through it for INBOX too),
				// and it's independent of the root query above, so it costs nothing extra here
				const inboxIds = t.Mailbox.query({accountId: token.accountId, filter: {name: 'INBOX'}});
				return {topIds, topMailboxes, inboxIds};
			});

			const top = topMailboxes.list || [];
			this.applyTemplateOutboxRoles(top, token);
			if (!token.isLocal)
			{
				await this.resolveHasChildren(client, token.accountId, top, effectiveSubscribedOnly);
				await this.resolveAclCapable(client, token, top);
			}

			const inboxId = inboxIds.ids?.[0] ?? null;
			// A subscribedOnly top-level query can legitimately come back without INBOX: some
			// real-world IMAP servers only ever report the auto-created special-use folders (Sent,
			// Trash, ...) as \Subscribed via LSUB and never INBOX itself, even though INBOX is
			// always accessible regardless of subscription state (ralf's report - acc_id=90, an
			// external IMAP account with the special folders as INBOX's own siblings). Unlike the
			// "server reports nothing subscribed at all" cyrus workaround (JmapShim::listChildIds()),
			// this is a non-empty result simply missing one specific entry, so that fallback never
			// triggers - without this, INBOX (and everything embedded under it below) would silently
			// vanish from the tree's top level entirely, even though the folder-management dialog
			// (which always passes subscribedOnly:false) shows it fine.
			if (effectiveSubscribedOnly && inboxId && !top.some((m : any) => m.id === inboxId))
			{
				const [{mailboxes : inboxMailboxes}] = await client.requestMany((t) => ({
					mailboxes: t.Mailbox.get({accountId: token.accountId, ids: [inboxId]}),
				}));
				const inboxMailbox = inboxMailboxes.list?.[0];
				if (inboxMailbox)
				{
					if (!token.isLocal)
					{
						await this.resolveAclCapable(client, token, [inboxMailbox]);
					}
					top.push(inboxMailbox);
				}
			}
			sortTopLevel(top);
			if (!inboxId)
			{
				return {top, inboxChildren: null};
			}
			// getMailboxChildren() would otherwise have to re-resolve this exact id itself the
			// next time anything needs it (eg. a row-fetch for INBOX, which happens almost
			// immediately since INBOX is the default selected folder)
			this.mailboxIds[profileID + '::INBOX'] = inboxId;
			const inboxChildren = await this.getMailboxChildren(profileID, inboxId, true, subscribedOnly);
			return {top, inboxChildren};
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.getRootFolders(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.getRootFolders(): failed, caller shows an error leaf', e);
			return null;
		}
	}

	/**
	 * Resolve "does this mailbox have children" for a level's worth of real-JMAP (RFC 8621)
	 * mailboxes - unlike JmapShim's local IMAP LIST attributes (\HasChildren/\HasNoChildren),
	 * standard JMAP's Mailbox object has NO hasChildren property at all, so every node would
	 * otherwise stay "assume expandable" until the user clicks it once and finds nothing (still
	 * correct, just a worse first impression). Batches one Mailbox/query{parentId, limit:1} per
	 * still-unknown node into a SINGLE extra HTTP round-trip via JMAP's own method-call batching
	 * (RFC 8620's core reason to exist) - not one round-trip per node. Deliberately NOT called
	 * for JmapShim/local-shim accounts (see getMailboxChildren()'s `!token.isLocal` guard): the
	 * shim's Mailbox/query "list children" mode does a real IMAP LIST per call, so doing this for
	 * every node in a level would reintroduce the exact N+1 IMAP round-trip problem
	 * JmapShim::mailboxGetInternal()'s own batching fix (mail/src/JmapShim.php) just solved.
	 * Best-effort: leaves hasChildren untouched (mutates in place) on any failure, since this is
	 * a cosmetic improvement over the already-correct self-correcting default, not worth failing
	 * the whole level fetch over.
	 *
	 * Also resolves hasSubscribedChildren for any namespace-root candidate ("user"/"shared", see
	 * folderTree.ts's isNamespaceRootName()) whenever subscribedOnly is in effect - hasChildren
	 * alone (any child, subscribed or not) isn't enough to decide whether that root belongs in a
	 * subscribedOnly view: folderTree.ts's isVisibleNamespaceRoot() needs to know whether at least
	 * one of its children is actually subscribed, or it would show an always-visible dead end
	 * whenever something is shared with this user but they haven't subscribed to any of it yet
	 * (ralf's report - still findable via the subscription dialog, which never sets
	 * subscribedOnly). A second, separate batched query (not folded into the loop above) since it
	 * only ever applies to the 0-2 namespace-root candidates a level can have, never worth doing
	 * for every mailbox.
	 *
	 * @param subscribedOnly the effective value already resolved by the caller (own param or
	 *  showAllFoldersInFolderPane preference, see getMailboxChildren()/getRootFolders())
	 */
	private async resolveHasChildren(client : JamClient, accountId : string, list : any[], subscribedOnly : boolean) : Promise<void>
	{
		const unknown = list.filter((m) => m.hasChildren === undefined || m.hasChildren === null);
		if (unknown.length)
		{
			try
			{
				const [checks] = await client.requestMany((t) => Object.fromEntries(
					unknown.map((m, i) => [`c${i}`, t.Mailbox.query({accountId, filter: {parentId: m.id}, limit: 1})])
				));
				unknown.forEach((m, i) => m.hasChildren = ((checks as any)[`c${i}`]?.ids?.length ?? 0) > 0);
			}
			catch (e)
			{
				console.error('MailJmap.resolveHasChildren(): failed, leaving hasChildren unresolved', e);
			}
		}

		if (!subscribedOnly) return;
		const roots = list.filter((m) => isNamespaceRootName(m.name) && m.hasChildren !== false);
		if (!roots.length) return;
		try
		{
			const [checks] = await client.requestMany((t) => Object.fromEntries(
				roots.map((m, i) => [`s${i}`, t.Mailbox.query({accountId, filter: {parentId: m.id, isSubscribed: true}, limit: 1})])
			));
			roots.forEach((m, i) => m.hasSubscribedChildren = ((checks as any)[`s${i}`]?.ids?.length ?? 0) > 0);
		}
		catch (e)
		{
			console.error('MailJmap.resolveHasChildren(): failed, leaving hasSubscribedChildren unresolved', e);
		}
	}

	/**
	 * Resolve whether ACL (folder access-rights) editing is available for this account, attaching
	 * it as `aclCapable` on the top level's own INBOX entry - MailApp.aclEnabled() (mail/js/app.ts)
	 * reads it from there (folderTree.ts's buildNode() copies it into the INBOX tree node's own
	 * `data.acl`, matching classic mail_tree.inc.php's exact node shape - its own "Set Acl
	 * capability for INBOX" comment - so aclEnabled() needed no changes at all). ACL editing is an
	 * account-level feature, never per-folder, hence only ever attached to INBOX.
	 *
	 * The local shim's classic IMAP ACL capability is resolved server-side instead
	 * (JmapShim::mailboxNode() - a live IMAP connection is already open by the time any mailbox is
	 * fetched for that account, so queryCapability('ACL') is free there) and arrives as
	 * `aclCapable` directly on its INBOX Mailbox object already - this only has anything to do for
	 * the real-JMAP/Stalwart case, where mail:share is an account capability (see doc/ai memory
	 * "mail-jmap-acl-plan"), not a per-mailbox property a plain Mailbox/get would ever return.
	 *
	 * A no-op if `list` isn't a top-level fetch (no role:'inbox' entry in it) - safe to call
	 * unconditionally.
	 *
	 * @param list mailboxes just fetched at any level - only acts if one of them is the account's
	 *  own top-level INBOX
	 */
	private async resolveAclCapable(client : JamClient, token : JmapToken, list : any[]) : Promise<void>
	{
		const inbox = list.find((m) => m.role === 'inbox');
		if (!inbox) return;
		try
		{
			const session = await client.session;
			const accountCapabilities = session.accounts?.[token.accountId]?.accountCapabilities ?? {};
			inbox.aclCapable = !!accountCapabilities['urn:ietf:params:jmap:mail:share'];
		}
		catch (e)
		{
			console.error('MailJmap.resolveAclCapable(): failed, leaving aclCapable unresolved', e);
		}
	}

	/**
	 * Resolve a canonical "/"-joined folder path to a JMAP Mailbox id, or null for the top level -
	 * thin wrapper around mailboxId() that avoids its per-segment walk choking on an empty path
	 * (folderPath.split('/') on '' yields [''], which would wrongly query for a mailbox literally
	 * named '').
	 */
	private async mailboxIdOrNull(client : JamClient, accountId : string, profileID : string, path : string) : Promise<string | null>
	{
		return path === '' ? null : this.mailboxId(client, accountId, profileID, path);
	}

	/**
	 * Public counterpart of mailboxIdOrNull() for callers outside this class (mail/js/app.ts's
	 * refreshFolderLevel(), which needs a parent's JMAP id to re-fetch its children after a
	 * folder CRUD change, without necessarily having one already cached on a tree node).
	 *
	 * @return null if this account has no usable JMAP access-token, *or* if path is '' (top level)
	 *  - same "null is a valid answer, not just a failure" contract getMailboxChildren() already has
	 */
	async resolveMailboxId(profileID : string, path : string) : Promise<string | null>
	{
		const token = await this.ensureToken(profileID);
		if (!token) return null;
		return this.mailboxIdOrNull(this.clients[profileID], token.accountId, profileID, path);
	}

	/**
	 * Drop every cached mailboxId() entry for `path` and anything nested under it (a rename/move/
	 * delete invalidates not just the folder itself but every descendant path cached under its old
	 * location) - without this, a later row-fetch for the old path would resolve a stale or
	 * now-wrong/nonexistent mailbox id.
	 */
	private invalidateMailboxIdCache(profileID : string, path : string) : void
	{
		const prefix = profileID + '::' + path;
		Object.keys(this.mailboxIds)
			.filter((key) => key === prefix || key.startsWith(prefix + '/'))
			.forEach((key) => delete this.mailboxIds[key]);
	}

	/**
	 * Every account is JMAP-eligible in principle (a real JMAP server, or JmapShim wrapping the
	 * exact same IMAP connection classic code would use) - "no usable token right now" means the
	 * underlying connection itself is down, not a reason to retry the identical operation via a
	 * second, classic code path (which would either hit the same failure, or silently mask a real
	 * bug in the JMAP/shim layer). Used by every mailbox-CRUD method below for their "no token"
	 * branch, in place of the classic-fallback `return false` they used to resolve there.
	 */
	private unreachableError() : JmapUserError
	{
		return new JmapUserError(this.egw.lang('Account not reachable'));
	}

	/**
	 * Create a new mailbox - the JMAP path for addFolder() (mail/js/app.ts).
	 *
	 * @param profileID
	 * @param parentPath canonical path of the parent folder, '' for the top level
	 * @param name new folder's (leaf) name
	 * @throws JmapUserError on any failure - see unreachableError()'s docblock for why there's no
	 *  classic fallback to retry via
	 */
	async createMailbox(profileID : string, parentPath : string, name : string) : Promise<void>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) throw this.unreachableError();
			const client = this.clients[profileID];
			const parentId = await this.mailboxIdOrNull(client, token.accountId, profileID, parentPath);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, create: {c0: {name, parentId}}}),
			}));
			if (!(result.created && result.created['c0']))
			{
				throw new JmapUserError(describeSetError(result.notCreated) ?? this.egw.lang('Failed to create folder %1', name));
			}
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			console.error('MailJmap.createMailbox(): failed', e);
			throw new JmapUserError(message ?? this.egw.lang('Account not reachable'));
		}
	}

	/**
	 * Rename a mailbox in place (same parent) - the JMAP path for renameFolder().
	 *
	 * @param profileID
	 * @param path canonical path of the folder to rename
	 * @param newName
	 * @throws JmapUserError on any failure - see unreachableError()'s docblock
	 */
	async renameMailbox(profileID : string, path : string, newName : string) : Promise<void>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) throw this.unreachableError();
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, update: {[id]: {name: newName}}}),
			}));
			if (result.updated && Object.prototype.hasOwnProperty.call(result.updated, id))
			{
				this.invalidateMailboxIdCache(profileID, path);
			}
			else
			{
				throw new JmapUserError(describeSetError(result.notUpdated) ?? this.egw.lang('Failed to rename folder %1', path));
			}
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			console.error('MailJmap.renameMailbox(): failed', e);
			throw new JmapUserError(message ?? this.egw.lang('Account not reachable'));
		}
	}

	/**
	 * Move a mailbox to a new parent - the JMAP path for moveFolder(). Same-account only
	 * (moveFolder() already rejects a cross-account move before ever calling this).
	 *
	 * @param profileID
	 * @param path canonical path of the folder to move
	 * @param newParentPath canonical path of the new parent, '' for the top level
	 * @throws JmapUserError on any failure - see unreachableError()'s docblock
	 */
	async moveMailbox(profileID : string, path : string, newParentPath : string) : Promise<void>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) throw this.unreachableError();
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);
			const newParentId = await this.mailboxIdOrNull(client, token.accountId, profileID, newParentPath);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, update: {[id]: {parentId: newParentId}}}),
			}));
			if (result.updated && Object.prototype.hasOwnProperty.call(result.updated, id))
			{
				this.invalidateMailboxIdCache(profileID, path);
			}
			else
			{
				throw new JmapUserError(describeSetError(result.notUpdated) ?? this.egw.lang('Failed to move folder %1', path));
			}
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			console.error('MailJmap.moveMailbox(): failed', e);
			throw new JmapUserError(message ?? this.egw.lang('Account not reachable'));
		}
	}

	/**
	 * Delete a mailbox - the JMAP path for deleteFolder().
	 *
	 * @param profileID
	 * @param path canonical path of the folder to delete
	 * @throws JmapUserError on any failure - see unreachableError()'s docblock
	 */
	async deleteMailbox(profileID : string, path : string) : Promise<void>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) throw this.unreachableError();
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, destroy: [id]}),
			}));
			if (Array.isArray(result.destroyed) && result.destroyed.includes(id))
			{
				this.invalidateMailboxIdCache(profileID, path);
			}
			else
			{
				throw new JmapUserError(describeSetError(result.notDestroyed) ?? this.egw.lang('Failed to delete folder %1', path));
			}
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			console.error('MailJmap.deleteMailbox(): failed', e);
			throw new JmapUserError(message ?? this.egw.lang('Account not reachable'));
		}
	}

	/**
	 * (Un)subscribe a mailbox - the JMAP path for subscribeFolder()/unsubscribeFolder().
	 *
	 * @param profileID
	 * @param path canonical path of the folder
	 * @param subscribed
	 * @throws JmapUserError on any failure - see unreachableError()'s docblock
	 */
	async setMailboxSubscribed(profileID : string, path : string, subscribed : boolean) : Promise<void>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) throw this.unreachableError();
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, update: {[id]: {isSubscribed: subscribed}}}),
			}));
			if (!(result.updated && Object.prototype.hasOwnProperty.call(result.updated, id)))
			{
				throw new JmapUserError(describeSetError(result.notUpdated) ?? this.egw.lang('Failed to subscribe/unsubscribe folder %1', path));
			}
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			console.error('MailJmap.setMailboxSubscribed(): failed', e);
			throw new JmapUserError(message ?? this.egw.lang('Account not reachable'));
		}
	}

	/**
	 * Build an empty-but-truthy get_rows-shaped result, so `fetchRows()` never returns `false`/
	 * a falsy resolution to `egw.dataFetch()` (api/js/jsapi/egw_data.ts) - a falsy answer there
	 * makes it fall through to a classic `ajax_get_rows` POST, but mail no longer registers a
	 * server-side `get_rows` callback (see class docblock), so that "fallback" only ever silently
	 * renders an empty grid with no explanation. Answering with this instead means every decline
	 * shows as a (possibly still empty, but at least explained) result, never a second, dead
	 * network round-trip.
	 */
	private static emptyRowsResult() : any
	{
		return {order: [], data: {}, total: 0, lastModification: Math.floor(Date.now() / 1000), readonlys: {}};
	}

	/**
	 * egw.dataRegisterFetch('mail', ...) callback: the only way NextMatch's row-fetch gets
	 * answered - there is no server-side row-fetch fallback (see class docblock), and this never
	 * falls through to the classic ajax_get_rows request either (see emptyRowsResult()): every
	 * decline is answered directly, with a surfaced error for the cases that are genuine failures.
	 * Never rejects.
	 *
	 * @param _execId unused
	 * @param _queriedRange {start, num_rows} or {refresh: [...]}
	 * @param _filters raw NextMatch activeFilters, incl. selectedFolder and sort as {id, asc}
	 * @param _widgetId unused
	 * @param _knownUids unused - JMAP always tells us the true total via calculateTotal
	 * @param _lastModification unused - we don't support incremental/only-changed fetches yet
	 */
	fetchRows(_execId : string, _queriedRange : any, _filters : any, _widgetId : string,
		_knownUids : string[], _lastModification : number) : Promise<any>
	{
		if (_queriedRange.refresh)
		{
			return this.refreshRows(typeof _queriedRange.refresh === 'string' ?
				[_queriedRange.refresh] : _queriedRange.refresh, !!_filters.filter2);
		}
		// _filters.selectedFolder is only set once the user actively picks a folder in this
		// session (see app.ts's "nm.activeFilters['selectedFolder'] = ..." call-sites) - same
		// "not always set, read it from foldertree" situation and fallback as app.ts:588-589.
		// On the very first fetch right after page load, even the foldertree widget itself can
		// still be mid-initialisation (returns nothing yet) - fall back to the "ActiveProfileID"
		// preference (written on profile switch by mail_ui::changeProfile(), and read
		// synchronously here since preferences are already loaded at this point, no
		// widget-readiness dependency needed). It won't reflect the last-viewed *folder* within
		// an account (nothing writes that anymore), only the last-viewed account - acceptable
		// for this one-time startup race, since a real folder click follows immediately after -
		// answer empty rather than falling through to the dead classic endpoint; no error, since
		// this is an expected transient state, not a failure.
		let selectedFolder = _filters.selectedFolder ||
			this.app.et2?.getWidgetById(this.app.nm_index + '[foldertree]')?.getValue() ||
			this.egw.preference('ActiveProfileID', 'mail');
		if (!selectedFolder)
		{
			return Promise.resolve(MailJmap.emptyRowsResult());
		}
		// egw.preference() auto-casts a purely-numeric stored value (just "42", no "::folder"
		// suffix yet - happens right after switching account, before a folder click persists
		// one) to a real JS number, not a string - guard the .match() below against that.
		selectedFolder = String(selectedFolder);
		if (!selectedFolder.match(/::/))
		{
			selectedFolder += '::INBOX';
		}
		if (_queriedRange.parent_id)
		{
			// doc/ai/projects/mail-threaded-view.md, Phase 1 - a thread-parent row's row_id ends
			// in "...::thread:<threadId>" (see emails2threadRow()); any other/legacy parent_id use
			// is unused by mail - answer empty rather than falling through to the dead classic
			// endpoint either way.
			const threadMatch = String(_queriedRange.parent_id).match(/thread:([^:]+)$/);
			if (!threadMatch)
			{
				return Promise.resolve(MailJmap.emptyRowsResult());
			}
			return this.getThreadMemberRows(threadMatch[1], selectedFolder, !!_filters.filter2)
				.then((result) : any => this.shapeFetchResult(result, selectedFolder))
				.catch((e) => this.handleFetchRowsError(e));
		}
		const query : JmapGetRowsQuery = {
			selectedFolder,
			start: _queriedRange.start,
			num_rows: _queriedRange.num_rows,
			cat_id: _filters.cat_id,
			search: _filters.search,
			filter: _filters.filter,
			startdate: _filters.startdate,
			enddate: _filters.enddate,
			filter2: _filters.filter2,
			// doc/ai/projects/mail-threaded-view.md, Phase 1 UI toggle - _filters.threaded arrives
			// as the same '1'/'' string convention as filter2 (MailApp.toggleThreaded()), coerced
			// to a real boolean here since JmapGetRowsQuery declares it as one
			threaded: !!_filters.threaded,
		};
		// sort is only split into order/sort strings server-side (Nextmatch.php), still a
		// {id, asc} object at this point - same normalisation buildSort() expects as input
		if (_filters.sort && typeof _filters.sort === 'object')
		{
			query.order = _filters.sort.id;
			query.sort = _filters.sort.asc ? 'ASC' : 'DESC';
		}

		return this.getRows(query).then((result) : any => this.shapeFetchResult(result, selectedFolder))
			.catch((e) => this.handleFetchRowsError(e));
	}

	/**
	 * Turn a getRows()/getThreadMemberRows() result into the shape egw.dataFetch() expects, shared
	 * by fetchRows()'s normal-list and thread-expand (`_queriedRange.parent_id`) branches.
	 */
	private shapeFetchResult(result : { rows : any[], total : number } | null, selectedFolder : string) : any
	{
		if (!result)
		{
			// account genuinely not reachable/JMAP-eligible right now (see
			// ensureToken()/getRows() docblocks) - there is no working classic fallback
			// anymore (see ProfileHandler::jmapBootstrap()'s own docblock: "the client
			// surfaces this as an error"), so say so instead of silently rendering an empty
			// grid with no explanation
			this.egw.message(this.egw.lang('Unable to connect to the mail server'), 'error');
			return MailJmap.emptyRowsResult();
		}
		this.enablePushOnce(selectedFolder);
		const data : Record<string, any> = {};
		result.rows.forEach((row) => data[row.row_id] = row);

		return {
			order: result.rows.map((row) => row.row_id),
			data,
			total: result.total,
			lastModification: Math.floor(Date.now() / 1000),
			readonlys: {},
		};
	}

	/** Shared fetchRows() rejection handler - see shapeFetchResult()'s docblock. */
	private handleFetchRowsError(e : any) : any
	{
		const message = e instanceof JmapUserError ? e.message : describeJmapError(e);
		this.egw.message(message || this.egw.lang('Unable to connect to the mail server'), 'error');
		console.error('MailJmap.fetchRows(): failed, resolving as an empty result', e);
		return MailJmap.emptyRowsResult();
	}

	/**
	 * Fire-and-forget (re-)enable client-side WS push for $selectedFolder's account, at most once
	 * per profile per JMAP-token lifetime (reset by ensureToken() whenever it gets a fresh token).
	 *
	 * Only does anything when token.enableWsPush is true (Api\Json\Push::onlyFallback(), see
	 * ProfileHandler::jmapBootstrap()) - i.e. no working push-server for this instance (e.g. shared
	 * hosting with no push-server process), so it's worth trying client-side JMAP push over the
	 * same WebSocket connection already used for requests instead. No regression risk either way:
	 * if this browser's WebSocket doesn't connect, the account had no working push before this
	 * either.
	 *
	 * The classic server-side path (Stalwart's native JMAP push subscriptions, or plain
	 * IMAP/Dovecot's mailbox-metadata push token registration) needs no client-side trigger at
	 * all - ProfileHandler::jmapBootstrap() calls it directly, server-side, whenever it's not
	 * relying on this WS path instead.
	 */
	private enablePushOnce(selectedFolder : string) : void
	{
		const profileID = selectedFolder.split('::', 1)[0];
		if (this.pushEnabled[profileID] || !this.tokens[profileID]?.enableWsPush)
		{
			return;
		}
		this.pushEnabled[profileID] = true;
		this.enableWsPush(profileID).catch((e) =>
		{
			console.error('MailJmap.enablePushOnce(): client-side WS push setup failed', e);
			delete this.pushEnabled[profileID];
		});
	}

	/**
	 * Register for JMAP push over the WebSocket transport (RFC 8887, JamWebSocketClient.onPush()) -
	 * only ever called when enablePushOnce() found token.enableWsPush true. Never fires if this
	 * profile's client never actually connects over WebSocket (see JamWebSocketClient's own
	 * transport-fallback design) - a callback registered while stuck on HTTP just never gets called.
	 */
	private async enableWsPush(profileID : string) : Promise<void>
	{
		const token = this.tokens[profileID];
		const client = this.clients[profileID];
		if (!token || !client)
		{
			return;
		}
		// Seed a baseline state before registering the callback, so the first real StateChange has
		// something to diff against - Foo/get's "state" reflects the current server-side state for
		// that type regardless of which (if any) ids were actually requested, so an empty ids list
		// is a cheap way to get it with no data transferred. Mirrors Api\Mail\Imap\Jmap::enablePush()
		// calling getStates() upfront, for the same reason.
		const [{emailState, mailboxState}] = await client.requestMany((t : any) => ({
			emailState: t.Email.get({accountId: token.accountId, ids: []}),
			mailboxState: t.Mailbox.get({accountId: token.accountId, ids: []})
		}));
		this.wsPushStates[profileID] = {
			[token.accountId]: {
				Email: emailState.state,
				Mailbox: mailboxState.state
			}
		};

		client.onPush((change : StateChange) => this.handleWsPush(profileID, change));
	}

	/** client.onPush() callback - fans out one StateChange frame's "changed" map by JMAP accountId. */
	private handleWsPush(profileID : string, change : StateChange) : void
	{
		Object.entries(change.changed || {}).forEach(([accountId, states]) =>
		{
			this.processWsPushStates(profileID, accountId, states as Record<string, string>).catch((e) =>
				console.error('MailJmap.handleWsPush(): failed to process a push notification', e));
		});
	}

	/**
	 * Diff a StateChange's new per-dataType states against the last known baseline, and - if
	 * anything actually changed since then - fetch and apply the delta. Updates the baseline
	 * unconditionally (even when there's nothing to diff against yet, or nothing changed), so a
	 * missing baseline only ever costs one skipped StateChange, never a stuck/repeated one.
	 */
	private async processWsPushStates(profileID : string, accountId : string, states : Record<string, string>) : Promise<void>
	{
		const client = this.clients[profileID];
		if (!client)
		{
			return;
		}
		const accountStates = this.wsPushStates[profileID] ??= {};
		const known = accountStates[accountId] ??= {};

		const sinceStates : Record<string, string> = {};
		Object.entries(states).forEach(([dataType, newState]) =>
		{
			// same scope as pushDataTypes passed to JamWebSocketClient above - Thread/Identity/... are
			// never subscribed to, but a defensive check costs nothing if the server ever sends one
			if (dataType !== 'Email' && dataType !== 'Mailbox')
			{
				return;
			}
			if (known[dataType] && known[dataType] !== newState)
			{
				sinceStates[dataType] = known[dataType];
			}
			known[dataType] = newState;
		});
		if (!Object.keys(sinceStates).length)
		{
			return;
		}

		const pushPayload = await this.buildWsPushPayload(client, profileID, accountId, sinceStates);
		pushPayload.forEach((pushData) => this.app.push(pushData));
	}

	/**
	 * JMAP Email/Mailbox changes -> classic egw_app.push() envelopes, client-side equivalent of
	 * Api\Mail\Imap\Jmap::pushCallback()'s "StateChange" case (which this deliberately mirrors
	 * property-for-property) - one requestMany() batch chaining {Email,Mailbox}/changes into
	 * {Email,Mailbox}/get via $ref(), same shape as Api\Mail\Jmap::getChanges().
	 *
	 * Deliberately uses this client's own native JMAP row-id shape
	 * (accountId::profileID::folderId::emailId, see messageReference()) for pushData.id, NOT the
	 * classic accountId::profileID::base64(folder)::uid shape pushCallback() builds server-side via
	 * emailId2uid() - this id is what NextMatch's nm.refresh() looks the row up by, and Stalwart rows
	 * are cached under the native JMAP shape (see mail-jmap-modernization.md's "Row-id scheme").
	 */
	private async buildWsPushPayload(client : JamWebSocketClient, profileID : string, accountId : string, sinceStates : Record<string, string>) : Promise<any[]>
	{
		// Deliberately no "destroyed" Foo/get call: a destroyed object can never be fetched (it
		// always resolves to notFound, never list - by JMAP semantics, not a Stalwart quirk), so
		// that $ref() would only ever resolve to an empty list even when it works. Worse, Stalwart
		// concretely rejects a *third* $ref() to the same preceding Foo/changes call with an
		// "invalidResultReference" error (confirmed live: /created and /updated resolve fine,
		// /destroyed doesn't) - and this client's all-or-nothing error handling (matching jmap-jam's
		// own, see JamWebSocketClient.requestMany()'s docblock) then rejects the *entire* batch,
		// silently breaking created/updated push too. Dropping the pointless destroyed call avoids
		// both problems; Api\Mail\Jmap::getChanges() has the same fundamental Email/get-on-destroyed
		// gap server-side (its lenient hand-rolled response parsing just doesn't throw on it).
		const [result] = await client.requestMany((t : any) =>
		{
			const calls : Record<string, any> = {};
			if (sinceStates.Mailbox)
			{
				const mailboxChanges = t.Mailbox.changes({accountId, sinceState: sinceStates.Mailbox});
				calls.mailboxChanges = mailboxChanges;
				calls.mailboxCreated = t.Mailbox.get({accountId, ids: mailboxChanges.$ref('/created')});
				calls.mailboxUpdated = t.Mailbox.get({accountId, ids: mailboxChanges.$ref('/updated')});
			}
			if (sinceStates.Email)
			{
				const emailChanges = t.Email.changes({accountId, sinceState: sinceStates.Email, maxChanges: 30});
				calls.emailChanges = emailChanges;
				calls.emailCreated = t.Email.get({
					accountId, ids: emailChanges.$ref('/created'),
					properties: ['id', 'mailboxIds', 'from', 'subject', 'preview', 'messageId']
				});
				calls.emailUpdated = t.Email.get({
					accountId, ids: emailChanges.$ref('/updated'),
					properties: ['id', 'mailboxIds', 'messageId', 'keywords']
				});
			}
			return calls;
		});

		const pushPayload : any[] = [];
		for (const [list, type] of [
			[result.mailboxCreated?.list, 'add'], [result.mailboxUpdated?.list, 'update']
		] as [any[] | undefined, string][])
		{
			for (const mailbox of list || [])
			{
				const pushData = await this.buildMailboxPush(client, profileID, accountId, mailbox, type);
				if (pushData)
				{
					pushPayload.push(pushData);
				}
			}
		}
		for (const [list, type] of [
			[result.emailCreated?.list, 'add'], [result.emailUpdated?.list, 'update']
		] as [any[] | undefined, string][])
		{
			for (const email of list || [])
			{
				const pushData = await this.buildEmailPush(client, profileID, accountId, email, type);
				if (pushData)
				{
					pushPayload.push(pushData);
				}
			}
		}
		// "destroyed" ids come straight from the *Changes responses already captured above (no
		// Foo/get - see this method's opening comment for why). A destroyed email's folder can
		// never be resolved via JMAP after the fact - MailApp.push() resolves it client-side
		// instead, via a wildcard egw.data search (email ids are unique per account), see
		// buildEmailDeletePush(). A destroyed mailbox's path, if this MailJmap instance ever
		// resolved it before, is already in folderPaths' cache; if not, nothing was ever
		// displayed for it to remove, so it's skipped.
		for (const emailId of result.emailChanges?.destroyed || [])
		{
			pushPayload.push(this.buildEmailDeletePush(profileID, emailId));
		}
		for (const folderId of result.mailboxChanges?.destroyed || [])
		{
			const folder = this.folderPaths[profileID]?.[folderId];
			if (folder)
			{
				pushPayload.push({
					app: 'mail',
					id: `${this.egw.user('account_id')}::${profileID}::${folderId}`,
					type: 'delete',
					acl: {folder}
				});
			}
		}
		return pushPayload;
	}

	/**
	 * Build a "delete" push envelope for a destroyed email whose folder is unknowable (see
	 * buildWsPushPayload()'s own comment for why) - the folderId segment is a literal "*"
	 * wildcard; MailApp.push() must resolve it via egw.dataRefreshUIDs() instead of an exact-match
	 * lookup, since email ids are unique per account. Shared by the WS path and (indirectly, same
	 * id shape) Api\Mail\Imap\Jmap::pushCallback()'s webhook equivalent.
	 */
	private buildEmailDeletePush(profileID : string, emailId : string) : any
	{
		return {
			app: 'mail',
			id: `${this.egw.user('account_id')}::${profileID}::*::${emailId}`,
			type: 'delete',
			acl: {}
		};
	}

	/** One Email/get result item -> a push() envelope - mirrors pushCallback()'s "email" switch branch. */
	private async buildEmailPush(client : JamWebSocketClient, profileID : string, accountId : string, email : any, type : string) : Promise<any | null>
	{
		const folderId = Object.keys(email.mailboxIds || {})[0];
		if (!folderId)
		{
			return null;
		}
		const folder = await this.folderId2path(client, profileID, accountId, folderId);
		if (folder === null)
		{
			return null;
		}
		const acl : Record<string, any> = {folder};
		switch (type)
		{
			case 'add':
				acl.event = 'MessageNew';
				acl.from = !email.from?.[0]?.name ? email.from?.[0]?.email : `${email.from[0].name} <${email.from[0].email}>`;
				acl.subject = email.subject;
				acl.snippet = (email.preview || '').trim();
				break;
			case 'update':
				// as with the classic path, we can't know the old flags - send the currently-set ones
				acl.event = 'Flags';
				acl.flags = Object.keys(email.keywords || {});
				break;
			case 'delete':
				acl.event = 'MessageDeleted';
				break;
		}
		return {
			app: 'mail',
			// EGroupware's own numeric account_id, NOT the "accountId" param above - that one is
			// Stalwart's own opaque JMAP accountId (e.g. "b"), a different value entirely, and using
			// it here would silently mismatch every row cached by email2row() (see its row_id).
			id: `${this.egw.user('account_id')}::${profileID}::${folderId}::${email.id}`,
			type,
			acl
		};
	}

	/** One Mailbox/get result item -> a push() envelope - mirrors pushCallback()'s "mailbox" switch branch. */
	private async buildMailboxPush(client : JamWebSocketClient, profileID : string, accountId : string, mailbox : any, type : string) : Promise<any | null>
	{
		const folder = await this.folderId2path(client, profileID, accountId, mailbox.id);
		if (folder === null)
		{
			return null;
		}
		const acl : Record<string, any> = {folder};
		if (type === 'update')
		{
			acl.unseen = mailbox.unreadEmails;
		}
		return {
			app: 'mail',
			// see buildEmailPush()'s comment: EGroupware's account_id, not the JMAP accountId param
			id: `${this.egw.user('account_id')}::${profileID}::${mailbox.id}`,
			type,
			acl
		};
	}

	/**
	 * Resolve a JMAP Mailbox id to its full "/"-joined path (e.g. "INBOX/Sub") - client-side
	 * equivalent of Api\Mail\Jmap::folderId2path(), same batched-Mailbox/get-chain-via-$ref()
	 * approach (4 ancestor levels per requestMany() round trip, looping for anything deeper),
	 * cached per profile for this MailJmap instance's lifetime. Returns null if the folder is gone
	 * by the time this resolves (a real race for a "destroyed" push item - see buildEmailPush()/
	 * buildMailboxPush() callers, which just drop that one item rather than push a broken envelope).
	 */
	private async folderId2path(client : JamWebSocketClient, profileID : string, accountId : string, folderId : string) : Promise<string | null>
	{
		const cache = this.folderPaths[profileID] ??= {};
		if (cache[folderId])
		{
			return cache[folderId];
		}

		// One Mailbox/get per ancestor level rather than jmap-jam's $ref() batching: $ref() resolves
		// a JSON pointer to a single scalar (here, the parent's own id) into the *value* of the next
		// call's "ids" argument, but Mailbox/get's ids must be an array - there's no JSON-pointer-only
		// way to wrap a resolved scalar back into a one-element array, so batching this walk into one
		// round trip isn't actually expressible via $ref(). Not worth the round-trip cost of a
		// deeper batched-ref scheme either: this only runs on push events, aggressively cached below.
		const parts : string[] = [];
		let id : string | null = folderId;
		while (id)
		{
			const [{list}] = await client.request(["Mailbox/get", {accountId, ids: [id], properties: ['parentId', 'name']}]);
			const mailbox = list?.[0];
			if (!mailbox)
			{
				break;	// folder no longer exists (a genuine race for a "destroyed" push item)
			}
			parts.push(parts.length === 0 && mailbox.name.toLowerCase() === 'inbox' ? 'INBOX' : mailbox.name);
			id = mailbox.parentId || null;
		}
		if (!parts.length)
		{
			return null;
		}
		return cache[folderId] = parts.reverse().join('/');
	}

	/**
	 * Rows a caller has patched into the egw data cache with a guess it is confident about ahead of confirmation.
	 * Keyed by storage uid (toStorageUid()), valued with Date.now() at the time of marking
	 * see markOptimistic().
	 *
	 * The guess is echoed back once and then trusted.
	 * That write's own success/failure is the real signal,
	 * and on failure it calls refreshRows() - an unmarked, genuinely fetching refresh.
	 *
	 * Markers expire because consumption is not guaranteed:
	 * Et2NextmatchDataProvider may fold our refresh into an in-flight one for the same row and never call us.
	 * Without the expiry, a leftover marker would make some later, genuine refresh
	 * (e.g. a push from another session) serve cached data instead of the server's real state.
	 */
	private optimisticRows = new Map<string, number>();

	/** How long an unconsumed markOptimistic() marker stays valid, ms - see optimisticRows. */
	private static readonly OPTIMISTIC_MAX_AGE = 1000;

	/** refreshRows() deals in bare (un-prefixed) provider row ids - see toProviderRowId(). */
	private toStorageUid(rowId : string) : string
	{
		return rowId.startsWith('mail::') ? rowId : `mail::${rowId}`;
	}

	/**
	 * Mark a row's egw data cache entry as an unconfirmed optimistic guess:
	 * the refresh the caller fires next (nm.refresh([uid], UPDATE_IN_PLACE), see MailApp.patchRow()) then re-renders from the cache instead of doing a JMAP round-trip.
	 * See optimisticRows for why that guess is trusted rather than verified.
	 */
	markOptimistic(uid : string) : void
	{
		this.optimisticRows.set(this.toStorageUid(uid), Date.now());
	}

	/**
	 * Consume rowId's marker and return the guessed row data it vouches for.
	 * Returns null if the row was never marked, the marker expired, or the cache entry is gone.
	 * The marker is consumed either way, so it can never outlive the one refresh it was made for.
	 */
	private takeOptimistic(rowId : string) : any
	{
		const uid = this.toStorageUid(rowId);
		const markedAt = this.optimisticRows.get(uid);
		this.optimisticRows.delete(uid);
		return markedAt !== undefined && Date.now() - markedAt < MailJmap.OPTIMISTIC_MAX_AGE ?
			this.egw.dataGetUIDdata(uid)?.data ?? null : null;
	}

	/**
	 * Handle NextMatch's single/multi-row refresh fetch
	 * (egw.dataRefreshUID(), fired after row actions like flag/delete and by push "update" events):
	 * a row carrying an optimistic guess (markOptimistic()) is echoed straight from the cache with no JMAP call,
	 * the rest are fetched via JMAP Email/get.
	 *
	 * @param fetchPreview matches getRows()'s "fetchPreview" behaviour:
	 *  only include the (comparatively expensive) message-body preview snippet
	 *  when the "Sneak preview in list" toggle (filter2 / mail.ShowDetails preference) is on.
	 *  Otherwise a row added or updated via this path
	 *  (e.g. a push 'add' held back while this tab wasn't active, then applied on return)
	 *  would show a snippet the user has explicitly turned off.
	 */
	private async refreshRows(rowIds : string[], fetchPreview : boolean) : Promise<any>
	{
		// Bare (un-prefixed) row ids as keys, like fetchRealRows():
		// dataFetch()'s parseServerResponse() prepends the storage prefix itself and would double-prefix.
		const data : Record<string, any> = {};
		const order : string[] = [];
		const fetchIds : string[] = [];
		for (const rowId of rowIds)
		{
			const guessed = this.takeOptimistic(rowId);
			if (guessed)
			{
				data[rowId] = guessed;
				order.push(rowId);
			}
			else
			{
				fetchIds.push(rowId);
			}
		}

		// Nothing optimistic - a plain fetch, passed straight through
		// (fetchRealRows() already resolves a real empty result and surfaces its own error message on failure).
		if (!order.length)
		{
			return this.fetchRealRows(fetchIds, fetchPreview);
		}

		if (fetchIds.length)
		{
			const fetched = await this.fetchRealRows(fetchIds, fetchPreview);
			if (fetched)
			{
				Object.assign(data, fetched.data);
				order.push(...fetched.order);
			}
		}

		return {
			order,
			data,
			total: order.length,
			lastModification: Math.floor(Date.now() / 1000),
			readonlys: {},
		};
	}

	/**
	 * The real JMAP Email/get round-trip refreshRows() uses for rows with no optimistic guess to echo -
	 * a reconciliation after a failed guess, a push notification from another session, or
	 * any other refresh with nothing already known locally.
	 *
	 * Rows JMAP no longer returns (deleted/moved) are simply left out of the result -
	 * dataFetch()'s parseServerResponse() already treats a requested-but-missing row as "gone"
	 * and removes it client-side.
	 *
	 * Resolves false on any error (row id from an unreachable/ineligible profile, network
	 * error) - dataFetch() then POSTs to ajax_get_rows, which resolves as an empty result
	 * (mail no longer registers a 'get_rows' callback), so the affected row(s) just don't
	 * get refreshed this time round rather than the whole fetch throwing.
	 *
	 * @param fetchPreview matches getRows()'s "fetchPreview" behaviour: only include the
	 *  (comparatively expensive) message-body preview snippet when the "Sneak preview in
	 *  list" toggle (filter2 / mail.ShowDetails preference) is on - otherwise a row added or
	 *  updated via this path (e.g. a push 'add' held back while this tab wasn't active, then
	 *  applied on return) would show a snippet the user has explicitly turned off.
	 */
	private async fetchRealRows(rowIds : string[], fetchPreview : boolean) : Promise<any>
	{
		try
		{
			// A malformed id (e.g. a folder id ending up here instead of a message row id - a
			// known, not yet root-caused issue, see feedback_et2nextmatch_mail_regression memory)
			// must not abort refreshing every OTHER row in the same batch - drop just that one
			const references : JmapMessageReference[] = [];
			for (const rowId of rowIds)
			{
				try
				{
					references.push(this.messageReference(rowId));
				}
				catch (e)
				{
					console.warn('MailJmap.fetchRealRows(): dropping malformed row id', rowId);
				}
			}
			if (!references.length)
			{
				// nothing valid left to refresh - not an error, but still answer directly rather
				// than falling through to the dead classic ajax_get_rows endpoint (see
				// fetchRows()/emptyRowsResult())
				return MailJmap.emptyRowsResult();
			}
			const groups = this.groupReferences(references);

			const data : Record<string, any> = {};
			const order : string[] = [];

			await Promise.all(Object.values(groups).map(async(refs) =>
			{
				const profileID = refs[0].profileID;
				const token = await this.ensureToken(profileID);
				if (!token)
				{
					throw new Error(`MailJmap.fetchRealRows(): profile ${profileID} is not JMAP-eligible`);
				}
				const properties = [
					'id', 'keywords', 'size', 'receivedAt', 'sentAt', 'subject',
					'from', 'to', 'cc', 'bcc', 'hasAttachment', MailJmap.MDN_HEADER_PROPERTY,
				];
				if (fetchPreview)
				{
					properties.push('preview');
				}
				const args : any = {
					accountId: token.accountId,
					ids: refs.map(ref => ref.emailId),
					properties,
				};
				if (token.isLocal)
				{
					// our shim's ids are plain per-mailbox IMAP UIDs, not globally-unique
					// real-JMAP ids - a standalone Email/get (no preceding Email/query in this
					// request) needs this local-only extension to know which mailbox to look
					// in (see JmapShim::emailGet())
					args.mailboxId = refs[0].mailboxId;
				}
				const [{emails}] = await this.clients[profileID].requestMany((t) => ({
					emails: t.Email.get(args) as any,
				}));
				const byId : Record<string, any> = {};
				(emails.list || []).forEach((email : any) => byId[email.id] = email);
				refs.forEach(ref =>
				{
					const email = byId[ref.emailId];
					if (email)
					{
						const row = this.email2row(email, ref.profileID, ref.mailboxId);
						data[row.row_id] = row;
						order.push(row.row_id);
					}
				});
			}));

			return {
				order,
				data,
				total: order.length,
				lastModification: Math.floor(Date.now() / 1000),
				readonlys: {},
			};
		}
		catch (e)
		{
			const message = e instanceof JmapUserError ? e.message : describeJmapError(e);
			this.egw.message(message || this.egw.lang('Unable to connect to the mail server'), 'error');
			console.error('MailJmap.fetchRealRows(): failed, resolving as an empty result', e);
			return MailJmap.emptyRowsResult();
		}
	}

	// message/attachment content-types that keep a message on the legacy server-side body path
	// (mail_ui::displayMessage() / get_load_email_data() / getdisplayableBody()) instead of the
	// fast client-side JMAP path below - see the plan's "Key findings" for why each needs to stay
	// server-side (S/MIME needs private key material server-side, TNEF decoding is a binary-format
	// library job, meeting invites render via calendar.calendar_uiforms::meeting()).
	// multipart/encrypted (PGP/MIME) is NOT in this set - Mailvelope decrypts entirely client-side
	// already and needs no server involvement at all, see findPgpPart()/fetchBody() below.
	private static readonly SPECIAL_CASE_TYPES = new Set([
		'multipart/signed',
		'application/pkcs7-mime', 'application/x-pkcs7-mime',
		'application/pkcs7-signature', 'application/x-pkcs7-signature',
		'text/calendar', 'application/ms-tnef',
	]);

	/**
	 * Cacheable Email/get, local shim only - never sent to a real JMAP server, which requires POST
	 * (RFC 8620 §3.3)
	 *
	 * jmap-jam's request()/requestMany() always POST, with no way to redirect the methodCalls into
	 * a URL query string, so this bypasses them entirely for this one call - sending methodCalls
	 * (and using, for parity/spec-shape) as GET query parameters instead, matching the GET branch
	 * mail/jmap.php now answers with Cache-Control/ETag headers. That lets the browser's own HTTP
	 * cache serve a previously viewed message's body without ever reaching jmap.php again - not an
	 * in-memory cache of our own (which would just grow forever across requests). Replicates only
	 * as much of jmap-jam's wire format/response demux (see its request-drafts.ts/helpers.ts) as
	 * this single, non-referenced call needs.
	 *
	 * The query string is built PHP http_build_query()-style (phpBuildQuery() below), not JSON -
	 * jmap.php reads it back via plain $_GET, no json_decode() needed - since a URL's length is
	 * bounded and JSON's quotes/braces/colons all cost 3 percent-encoded bytes each for no benefit
	 * here.
	 *
	 * @param client the profile's JamClient, only used here for its already-resolved session/apiUrl
	 * @param args Email/get args (accountId, ids, properties, ...)
	 * @param signal optional AbortSignal, same as the requestMany() path this replaces
	 * @return the raw Email/get result data ({accountId, list, notFound})
	 */
	private async emailGetViaCacheableGet(client : JamClient, args : any, signal? : AbortSignal) : Promise<any>
	{
		const {apiUrl} = await client.session;
		const methodCalls = [["Email/get", args, "emails"]];
		const using = ["urn:ietf:params:jmap:core", "urn:ietf:params:jmap:mail"];
		const response = await fetch(apiUrl+'?'+this.phpBuildQuery({methodCalls, using}), {
			method: 'GET',
			headers: {Accept: 'application/json'},
			signal,
		});
		if (!response.ok)
		{
			throw await response.json().catch(() => response.statusText);
		}
		const {methodResponses} = await response.json();
		const found = (methodResponses || []).find((r : any) => r[2] === 'emails');
		if (!found)
		{
			throw new Error('Email/get: no matching response');
		}
		if (found[0] === 'error')
		{
			throw found[1];
		}
		return found[1];
	}

	/**
	 * PHP http_build_query()-equivalent query string, for emailGetViaCacheableGet() above
	 *
	 * A plain array of scalars uses PHP's bare "[]" (each occurrence just appends) - eg.
	 * using[]=urn1&using[]=urn2 - since nothing else needs to correlate with its index. An array
	 * containing objects/arrays (methodCalls: one triple per call) instead needs an explicit index
	 * per element - eg. methodCalls[0][0]=...&methodCalls[0][1][accountId]=... - so that a call's
	 * several fields land back together under the same $_GET['methodCalls'][0] on the PHP side:
	 * bare "[]" allocates a fresh top-level index on EVERY occurrence, even for sibling keys of the
	 * same element, so eg. "a[][b]=1&a[][c]=2" parses back as two separate entries, not one {b,c}.
	 *
	 * @param params
	 * @return the query string, without a leading "?"
	 */
	private phpBuildQuery(params : Record<string, any>) : string
	{
		return Object.entries(params).flatMap(([key, value]) => this.phpQueryParts(value, key)).join('&');
	}

	/** Recursive helper for phpBuildQuery() - one PHP-style bracket key per scalar leaf value */
	private phpQueryParts(value : any, prefix : string) : string[]
	{
		if (Array.isArray(value))
		{
			const scalarList = value.every((v) => v === null || typeof v !== 'object');
			return value.flatMap((v, i) => this.phpQueryParts(v, scalarList ? `${prefix}[]` : `${prefix}[${i}]`));
		}
		if (value !== null && typeof value === 'object')
		{
			return Object.entries(value).flatMap(([k, v]) => this.phpQueryParts(v, `${prefix}[${k}]`));
		}
		if (typeof value === 'boolean')
		{
			value = value ? 1 : 0;
		}
		return [`${encodeURIComponent(prefix)}=${encodeURIComponent(String(value))}`];
	}

	/**
	 * Fetch and assemble one message's body directly via JMAP (Stalwart, or the local IMAP shim)
	 *
	 * Fetches bodyStructure/textBody/htmlBody/attachments/bodyValues in one optimistic round trip
	 * (fetchAllBodyValues) and inspects the returned structure for the special cases above -
	 * discarding the fetched bodyValues and returning {special:true} if found, so the caller
	 * (app.ts) falls back to the existing server-rendered iframe unchanged. Never throws - any
	 * failure (unreachable profile, network error) also resolves {special:true}, for the same
	 * fallback reason.
	 */
	async fetchBody(rowId: string, htmlOptions?: string, signal?: AbortSignal): Promise<JmapBodyResult>
	{
		try
		{
			const ref = this.messageReference(rowId);
			const token = await this.ensureToken(ref.profileID);
			if (!token)
			{
				return {special: true};
			}
			const args : any = {
				accountId: token.accountId,
				ids: [ref.emailId],
				properties: ['bodyStructure', 'textBody', 'htmlBody', 'attachments', 'bodyValues'],
				fetchAllBodyValues: true,
			};
			if (token.isLocal)
			{
				args.mailboxId = ref.mailboxId;
			}
			// the local shim's body content is immutable per uid/part, so route it through a plain
			// GET (see emailGetViaCacheableGet()'s docblock) instead of jmap-jam's usual POST -
			// lets the browser's own HTTP cache serve a re-opened message without hitting jmap.php
			// again at all. A real JMAP server (Stalwart) always requires POST, so keep using
			// jmap-jam's requestMany() there unchanged.
			const emails = token.isLocal ?
				await this.emailGetViaCacheableGet(this.clients[ref.profileID], args, signal) :
				(await this.clients[ref.profileID].requestMany((t) => ({
					emails: t.Email.get(args) as any,
				}), signal ? {fetchInit: {signal}} : undefined))[0].emails;
			const email = (emails.list || [])[0];
			if (!email || this.isSpecialCase(email.bodyStructure))
			{
				return {special: true};
			}
			// PGP/MIME: no server involvement needed at all (see SPECIAL_CASE_TYPES' docblock) -
			// the "body" is the raw ciphertext/armored-text of multipart/encrypted's 2nd sub-part,
			// downloaded as a blob (same mechanism resolveInlineImages() uses for cid: images) and
			// rendered through the plain-text path so Mailvelope's mailvelopeDisplay()
			// (mail/js/app.ts) finds it in the `td.td_display > pre` it already scans for.
			const pgpPart = this.findPgpPart(email.bodyStructure);
			const html = pgpPart ?
				this.wrapDocument(MailJmap.textToHtml(await this.downloadPartText(ref.profileID, token, pgpPart))) :
				this.assembleBodyHtml(email, htmlOptions);
			return {
				special: false,
				html,
				attachments: email.attachments || [],
				profileID: ref.profileID,
				accountId: token.accountId,
				isLocal: token.isLocal,
			};
		}
		catch (e)
		{
			if (signal?.aborted)
			{
				// caller ignores an aborted request's result - skip the noisy log
				return {special: true};
			}
			console.error('MailJmap.fetchBody(): failed, falling back to the server-rendered body', e);
			return {special: true};
		}
	}

	/** Depth-first walk of bodyStructure/subParts for any SPECIAL_CASE_TYPES / winmail.dat match */
	private isSpecialCase(part : any) : boolean
	{
		if (!part)
		{
			return false;
		}
		if (MailJmap.SPECIAL_CASE_TYPES.has((part.type || '').toLowerCase()) ||
			(part.name || '').toLowerCase() === 'winmail.dat')
		{
			return true;
		}
		return (part.subParts || []).some((sub : any) => this.isSpecialCase(sub));
	}

	/**
	 * Depth-first search for a multipart/encrypted part, returning its 2nd sub-part (RFC 3156: 1st
	 * is the application/pgp-encrypted control part, 2nd is the actual ciphertext/armored blob) -
	 * mirrors Mail::getMessageAttachments()'s "skip multipart/encrypted incl. its two sub-parts"
	 * comment (api/src/Mail.php:6169), i.e. that 2nd sub-part is deliberately never listed as a
	 * regular attachment either, same convention this follows.
	 */
	private findPgpPart(part : any) : any | null
	{
		if (!part)
		{
			return null;
		}
		if ((part.type || '').toLowerCase() === 'multipart/encrypted' && part.subParts?.length >= 2)
		{
			return part.subParts[1];
		}
		for (const sub of part.subParts || [])
		{
			const found = this.findPgpPart(sub);
			if (found)
			{
				return found;
			}
		}
		return null;
	}

	/** Download one body part's raw bytes via JMAP Blob download, decoded as UTF-8 text */
	private async downloadPartText(profileID : string, token : JmapToken, part : any) : Promise<string>
	{
		const response = await this.clients[profileID].downloadBlob({
			accountId: token.accountId,
			blobId: part.blobId,
			mimeType: part.type || 'application/octet-stream',
			fileName: part.name || 'part',
		});
		return response.text();
	}

	/**
	 * Assemble a sanitized, self-contained HTML document for the body iframe's `srcdoc`
	 *
	 * Loads the same `preview.js` (mailto/internal-EGroupware-link activation) the server-rendered
	 * body already uses, unmodified - a `srcdoc` iframe without a `sandbox` attribute is same-
	 * origin with the parent, exactly like the server-rendered one, so this needs no separate
	 * reimplementation of that logic. `<meta>`/`<base>` are explicitly forbidden from the sanitized
	 * body content itself, so a malicious/buggy message can't smuggle in a competing CSP, a
	 * `<meta http-equiv="refresh">`, or hijack relative URLs.
	 *
	 * cid: image references are rewritten to a data-cid attribute here (src is cleared) -
	 * resolved asynchronously after render by resolveInlineImages(), same as external images
	 * already are (resolveExternalImages(), which stashes the blocked URL in `alt` rather than
	 * `src` for the same reason). Leaving the real "cid:" value in `src` would still work once
	 * resolveInlineImages() replaces it on load, but the browser attempts to fetch every `src` the
	 * moment the srcdoc HTML is parsed - long before the 'load' event resolveInlineImages() waits
	 * for - and "cid:" isn't a scheme any img-src CSP can allow, so that doomed attempt always
	 * logs a CSP violation first regardless of how quickly it's swapped out afterward.
	 */
	private assembleBodyHtml(email : any, htmlOptions? : string) : string
	{
		const htmlParts : any[] = email.htmlBody || [];
		const textParts : any[] = email.textBody || [];
		const useHtml = htmlParts.length > 0 && htmlOptions !== 'only_if_no_text';
		const part = useHtml ? htmlParts[0] : (textParts[0] || htmlParts[0]);
		const raw = part ? (email.bodyValues?.[part.partId]?.value || '') : '';
		const isHtml = !!part && (useHtml || !textParts.length);

		let body : string;
		if (isHtml)
		{
			body = DOMPurify.sanitize(raw, {
				FORBID_TAGS: ['script', 'meta', 'base', 'object', 'embed', 'applet', 'iframe'],
				ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto|tel|cid|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
			});
			body = MailJmap.deferCidImages(body);
		}
		else
		{
			body = MailJmap.textToHtml(raw);
		}
		return this.wrapDocument(body);
	}

	/**
	 * Move every `<img src="cid:...">` to `data-cid="..."` with `src` cleared, so the browser
	 * never attempts (and CSP-blocks) the "cid:" scheme on initial render - see
	 * assembleBodyHtml()'s own docblock. resolveInlineImages() looks for `img[data-cid]` instead.
	 */
	private static deferCidImages(html : string) : string
	{
		const doc = new DOMParser().parseFromString(html, 'text/html');
		doc.querySelectorAll('img[src^="cid:"]').forEach((img) =>
		{
			img.setAttribute('data-cid', img.getAttribute('src').substring(4));
			img.removeAttribute('src');
		});
		return doc.body.innerHTML;
	}

	/**
	 * Wrap already-sanitized body HTML into a self-contained document for the body iframe's
	 * `srcdoc` - shared by assembleBodyHtml() (normal mail) and fetchBody()'s PGP path.
	 *
	 * Loads the same `preview.js` (mailto/internal-EGroupware-link activation) the server-rendered
	 * body already uses, unmodified - a `srcdoc` iframe without a `sandbox` attribute is same-
	 * origin with the parent, exactly like the server-rendered one, so this needs no separate
	 * reimplementation of that logic. `<meta>`/`<base>` are explicitly forbidden from the sanitized
	 * body content itself (assembleBodyHtml()'s DOMPurify config), so a malicious/buggy message
	 * can't smuggle in a competing CSP, a `<meta http-equiv="refresh">`, or hijack relative URLs.
	 *
	 * cid: image references are left as-is here - resolved asynchronously after render by
	 * resolveInlineImages(), same as external images already are (resolveExternalImages()).
	 */
	private wrapDocument(body : string) : string
	{
		// same directive set the current server-rendered response sets via HTTP header
		// (mail_ui::get_load_email_data(), class.mail_ui.inc.php:2993-3000: script-src 'self' to
		// load preview.js below, img-src additionally allows blob: for Stalwart inline-image
		// downloads, see resolveInlineImages(), alongside the data: URIs cid images already used)
		const csp = "frame-src 'none'; connect-src 'none'; manifest-src 'none'; script-src 'self'; " +
			"img-src http: blob: data:; media-src https: http: data:";

		return `<!DOCTYPE html><html><head><meta charset="utf-8">` +
			`<meta http-equiv="Content-Security-Policy" content="${csp}">` +
			`<link rel="stylesheet" href="${this.egw.link('/mail/templates/default/preview.css')}">` +
			`<script defer src="${this.egw.link('/mail/js/preview.js')}"></script>` +
			`</head><body><div class="mailDisplayBody"><table width="100%" style="table-layout:fixed">` +
			`<tr><td class="td_display">${body}</td></tr></table></div></body></html>`;
	}

	/** text/plain -> escaped, auto-linked, <pre>-wrapped HTML - mirrors mail_ui::getdisplayableBody() */
	private static textToHtml(text : string) : string
	{
		const escaped = (text || '')
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		const linked = escaped.replace(/((?:https?:\/\/|www\.)[^\s<>"]+)/gi, (url) =>
		{
			const href = url.toLowerCase().startsWith('http') ? url : 'https://' + url;
			return `<a href="${href}" target="_blank" rel="noopener noreferrer">${url}</a>`;
		});
		return DOMPurify.sanitize(`<pre>${linked}</pre>`, {ALLOWED_TAGS: ['pre', 'a'], ALLOWED_ATTR: ['href', 'target', 'rel']});
	}

	/**
	 * Resolve `cid:` image references left untouched by assembleBodyHtml(), after the srcdoc body
	 * has actually rendered - same deliberately-asynchronous, post-load pattern app.ts's
	 * resolveExternalImages() already uses for remote images.
	 *
	 * Uniformly via JMAP Blob download (client.downloadBlob()) for both backends - Stalwart's own
	 * blobId downloads directly from Stalwart; the local shim's blobId is self-describing
	 * (JmapShim.php's bodyPartToJmap() docblock) and resolved by its own download() endpoint
	 * (mail/jmap.php's "download" branch) - no more mail_ui::displayImage() dependency for this
	 * fast path (that endpoint is still used by the legacy server-rendered fallback page). Object
	 * URLs are revoked again the next time this row's body is (re-)rendered.
	 */
	private objectUrls : Record<string, string[]> = {};

	async resolveInlineImages(doc : Document, rowId : string, result : Extract<JmapBodyResult, { special : false }>) : Promise<void>
	{
		(this.objectUrls[rowId] || []).forEach(url => URL.revokeObjectURL(url));
		this.objectUrls[rowId] = [];

		// assembleBodyHtml()'s deferCidImages() already moved "cid:..." out of src into data-cid
		// (and cleared src) before this HTML ever reached the iframe, so the browser never
		// attempted - and CSP-blocked - the "cid:" scheme in the first place
		const images = Array.from(doc.querySelectorAll('img[data-cid]')) as HTMLImageElement[];
		if (!images.length)
		{
			return;
		}
		// RFC 2045's Content-ID header value is conventionally written wrapped in angle brackets
		// (eg. "<checkmk_logo.png>"), and a real JMAP server (Stalwart) can return
		// EmailBodyPart.cid as that raw header value verbatim - but the "cid:" URI scheme
		// (RFC 2392, and this widget's own data-cid attribute) never includes the brackets, so an
		// unstripped "<checkmk_logo.png>" key here would never match
		const stripCidBrackets = (cid : string) => cid.trim().replace(/^</, '').replace(/>$/, '');

		const byCid : Record<string, any> = {};
		result.attachments.forEach((att : any) =>
		{
			if (att.cid)
			{
				byCid[stripCidBrackets(att.cid)] = att;
			}
		});

		await Promise.all(images.map(async(img) =>
		{
			const cid = stripCidBrackets(decodeURIComponent(img.getAttribute('data-cid')));
			const attachment = byCid[cid];
			if (!attachment)
			{
				return;
			}
			try
			{
				const response = await this.clients[result.profileID].downloadBlob({
					accountId: result.accountId,
					blobId: attachment.blobId,
					mimeType: attachment.type,
					fileName: attachment.name || 'image',
				});
				const url = URL.createObjectURL(await response.blob());
				this.objectUrls[rowId].push(url);
				img.src = url;
			}
			catch (e)
			{
				console.error('MailJmap.resolveInlineImages(): failed for cid', cid, e);
			}
		}));
	}

	/**
	 * Download one attachment via JMAP Blob download and trigger a browser save - same
	 * client.downloadBlob() + Blob mechanism resolveInlineImages() already uses to display cid:
	 * images, uniformly for both backends (Stalwart's own blobId; the local shim's self-describing
	 * one, resolved by JmapShim's download() endpoint) - no mail_ui::getAttachment() IMAP fetch.
	 *
	 * @param profileID
	 * @param blobId as returned by mail_ui::jmapAttachmentsToLegacy() in the row's attachmentsBlock
	 * @param filename suggested filename for the save dialog
	 * @param mimeType
	 * @throws JmapUserError on any failure - there's no classic fallback (see
	 *  folderTreeAutoload()'s docblock in app.ts for why)
	 */
	async downloadAttachment(profileID : string, blobId : string, filename : string, mimeType : string) : Promise<void>
	{
		let url : string;
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token)
			{
				throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
			}
			const response = await this.clients[profileID].downloadBlob({
				accountId: token.accountId,
				blobId,
				mimeType: mimeType || 'application/octet-stream',
				fileName: filename || 'attachment',
			});
			url = URL.createObjectURL(await response.blob());
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			console.error('MailJmap.downloadAttachment(): failed', e);
			throw new JmapUserError(describeJmapError(e) ?? this.egw.lang('Unable to connect to the mail server'));
		}
		try
		{
			const link = document.createElement('a');
			link.href = url;
			link.download = filename || 'attachment';
			document.body.appendChild(link);
			link.click();
			link.remove();
		}
		finally
		{
			// revoke after the click has been processed, not synchronously
			window.setTimeout(() => URL.revokeObjectURL(url), 1000);
		}
	}

	/**
	 * Raw message headers, for the "view header" action - fetches the whole message as a raw text
	 * blob (client.downloadBlob() against the message's whole-message blobId, same mechanism
	 * downloadAttachment()/PGP already use for a specific part) and slices at the first blank line
	 * client-side, byte-identical to what Api\Mail::getMessageRawHeader() already returns - no
	 * dedicated IMAP HEADERTEXT fetch needed.
	 *
	 * @param rowId
	 * @throws JmapUserError on any failure - there's no classic fallback (see
	 *  folderTreeAutoload()'s docblock in app.ts for why)
	 */
	async fetchRawHeader(rowId : string) : Promise<string>
	{
		try
		{
			const reference = this.messageReference(rowId);
			const token = await this.ensureToken(reference.profileID);
			if (!token)
			{
				throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
			}
			const args : any = {accountId: token.accountId, ids: [reference.emailId], properties: ['blobId']};
			if (token.isLocal)
			{
				// standalone Email/get (no preceding Email/query in this request) needs our shim's
				// local-only mailboxId extension - see JmapShim::emailGet(), same as refreshRows()
				args.mailboxId = reference.mailboxId;
			}
			const [{emails}] = await this.clients[reference.profileID].requestMany((t) => ({
				emails: t.Email.get(args) as any,
			}));
			const blobId = (emails.list || [])[0]?.blobId;
			if (!blobId)
			{
				throw new JmapUserError(this.egw.lang('Unable to resolve the message blobId'));
			}
			const response = await this.clients[reference.profileID].downloadBlob({
				accountId: token.accountId,
				blobId,
				mimeType: 'message/rfc822',
				fileName: 'header',
			});
			const text = await response.text();
			const match = text.match(/\r?\n\r?\n/);
			return match ? text.slice(0, match.index) : text;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			console.error('MailJmap.fetchRawHeader(): failed', e);
			throw new JmapUserError(describeJmapError(e) ?? this.egw.lang('Unable to connect to the mail server'));
		}
	}

	/**
	 * Get a valid access-token for $profileID, requesting a fresh one from the server if needed
	 *
	 * The refresh-token never leaves the server: we just re-request this same bootstrap
	 * endpoint shortly before the access-token expires, instead of reacting to a 401.
	 *
	 * @return null if $profileID is not JMAP-eligible (not Stalwart, MFA required, ...)
	 */
	private async ensureToken(profileID : string) : Promise<JmapToken | null>
	{
		// avoid re-requesting the bootstrap endpoint on every single get_rows call for
		// accounts that just aren't JMAP-eligible (fetchRows() is now on that hot path)
		if ((this.ineligibleUntil[profileID] || 0) > Date.now())
		{
			return null;
		}
		const cached = this.tokens[profileID];
		if (cached && cached.expires_at > Date.now())
		{
			return cached;
		}
		if (!this.tokenPromises[profileID])
		{
			this.tokenPromises[profileID] = this.egw.request('mail.mail_ui.ajax_jmapBootstrap', [profileID])
				.then((data : any) : JmapToken | null =>
				{
					window.clearTimeout(this.refreshTimers[profileID]);

					if (!data || !data.access_token)
					{
						delete this.tokens[profileID];
						delete this.clients[profileID];
						this.ineligibleUntil[profileID] = Date.now() + MailJmap.INELIGIBLE_RECHECK_INTERVAL;
						return null;
					}
					const token : JmapToken = {
						sessionUrl: data.sessionUrl,
						accountId: data.accountId,
						access_token: data.access_token,
						expires_at: Date.now() + Math.max(0, data.expires_in - 60) * 1000,
						isLocal: !!data.isLocal,
						supportsThreading: !!data.supportsThreading,
						customLabels: data.customLabels || {},
						trashFolder: data.trashFolder,
						junkFolder: data.junkFolder,
						templatesFolder: data.templatesFolder,
						outboxFolder: data.outboxFolder,
						enableWsPush: !!data.enableWsPush,
					};
					if (Object.keys(token.customLabels).length)
					{
						this.app.customLabels = token.customLabels;
						this.app.updateCustomLabelStylesheet();
					}
					this.tokens[profileID] = token;
					// close the outgoing client's WebSocket (if any) explicitly, rather than leaving
					// it to be garbage-collected - this replacement happens on every token refresh
					// (~hourly), and the old socket has nothing more to do once its bearer token
					// stops being valid.
					this.clients[profileID]?.close();
					this.clients[profileID] = new JamWebSocketClient({
						sessionUrl: token.sessionUrl,
						bearerToken: token.access_token,
						// Stalwart's JMAP-over-WebSocket upgrade only accepts credentials via a real
						// Authorization header (confirmed against its source - see
						// doc/ai/projects/mail-jmap-jam-websocket.md), which browsers cannot set on a
						// WebSocket handshake. Our nginx vhost in front of Stalwart converts an
						// access_token query parameter back into that header for exactly this path
						// (/jmap/ws only - every other JMAP request keeps using the real header
						// directly, unaffected). No-op for the local IMAP shim: it never advertises
						// the websocket capability, so this transform is simply never invoked.
						transformWebSocketUrl: (url : string) : string =>
						{
							const transformed = new URL(url);
							transformed.searchParams.set('access_token', token.access_token);
							return transformed.toString();
						},
						// Same scope as the classic server-side subscription's SUBSCRIBTION_TYPES
						// (Api\Mail\Imap\Jmap) - only relevant if enableWsPush actually registers a
						// callback (see enableWsPush() below), otherwise never sent at all.
						pushDataTypes: ['Email', 'Mailbox']
					});
					// a stale-CSP session-fetch failure for this client is handled by the
					// securitypolicyviolation listener installed in the constructor (see
					// onCspViolation()'s docblock for why that event, not this promise, is the
					// right thing to react to) - nothing to do here beyond having set
					// this.tokens[profileID] above, which is what that handler matches against
					// fresh token: renew the mail server's push subscription/token again too,
					// next time we know which folder is being viewed (see fetchRows()) - and forget
					// any client-side WS push baseline, a fresh client means a fresh onPush()
					// registration is needed too
					delete this.pushEnabled[profileID];
					delete this.wsPushStates[profileID];
					// proactively refresh before expiry, so we never react to a 401
					this.refreshTimers[profileID] = window.setTimeout(
						() => this.ensureToken(profileID), Math.max(10, data.expires_in - 60) * 1000);

					return token;
				})
				.finally(() => delete this.tokenPromises[profileID]);
		}
		return this.tokenPromises[profileID];
	}

	/**
	 * Resolve a "folder/path" (EGroupware convention) to a JMAP Mailbox id, walking parents
	 * one level at a time and caching the result - client-side equivalent of the server's
	 * Mail\Jmap::getMailboxId().
	 */
	private async mailboxId(client : JamClient, accountId : string, profileID : string, folderPath : string) : Promise<string>
	{
		const cacheKey = profileID + '::' + folderPath;
		if (this.mailboxIds[cacheKey])
		{
			return this.mailboxIds[cacheKey];
		}
		try
		{
			let parentId : string;
			let id : string;
			for (const part of folderPath.split('/'))
			{
				const [{ids}] = await client.requestMany((t) => ({
					ids: t.Mailbox.query({
						accountId,
						filter: parentId ? {name: part, parentId} : {name: part},
					}),
				}));
				id = ids.ids?.[0];
				if (!id)
				{
					throw new JmapUserError(this.egw.lang("Folder '%1' not found", folderPath));
				}
				parentId = id;
			}
			return this.mailboxIds[cacheKey] = id;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			console.error('MailJmap.mailboxId(): failed', e);
			throw new JmapUserError(describeJmapError(e) ?? this.egw.lang('Unable to connect to the mail server'));
		}
	}

	/**
	 * Map a JmapGetRowsQuery (cat_id/search/filter/startdate/enddate) to a JMAP
	 * Email/query filter, mirroring Mail::createIMAPFilter()'s semantics.
	 *
	 * Note: filter=deleted has no JMAP equivalent - messages with the IMAP \Deleted keyword
	 * are never exposed via JMAP at all (RFC 8621 §4.1.1), so that case is simply ignored.
	 */
	private buildFilter(query : JmapGetRowsQuery, mailboxId : string) : EmailFilter
	{
		const conditions : EmailFilter[] = [{inMailbox: mailboxId}];
		const catId = (query.cat_id || '').toLowerCase();
		const searchStr = query.search || '';

		if (searchStr)
		{
			let textFilter : EmailFilter = null;
			switch (catId)
			{
				case '':
				case 'quick':
				case 'quickwithcc':
				case 'bydate':
					textFilter = this.buildTokenizedFilter(searchStr,
						catId === 'quickwithcc' ? ['subject', 'from', 'to', 'cc'] : ['subject', 'from', 'to']);
					break;
				case 'subject':
				case 'from':
				case 'to':
				case 'cc':
				case 'bcc':
					textFilter = this.buildTokenizedFilter(searchStr, [catId]);
					break;
				case 'body':
					textFilter = this.buildTokenizedFilter(searchStr, ['body']);
					break;
				case 'text':
					textFilter = this.buildTokenizedFilter(searchStr, ['text']);
					break;
				case 'larger':
					conditions.push({minSize: this.parseSize(searchStr)});
					break;
				case 'smaller':
					conditions.push({maxSize: this.parseSize(searchStr)});
					break;
			}
			if (textFilter)
			{
				conditions.push(textFilter);
			}
		}

		if (query.startdate)
		{
			conditions.push({after: this.toUTCDate(query.startdate)});
		}
		if (query.enddate)
		{
			// our "enddate" is inclusive of that day, JMAP's "before" is exclusive - same
			// +1 day adjustment as Mail.php's createIMAPFilter() BEFORE handling
			conditions.push({before: this.toUTCDate(query.enddate, 1)});
		}

		const status = (query.filter || '').toLowerCase();
		switch (status)
		{
			case 'flagged':
				conditions.push({hasKeyword: '$flagged'});
				break;
			case 'unseen':
				conditions.push({notKeyword: '$seen'});
				break;
			case 'answered':
				conditions.push({hasKeyword: '$answered'});
				break;
			case 'seen':
				conditions.push({hasKeyword: '$seen'});
				break;
			case 'keyword1':
			case 'label1':
			case 'keyword2':
			case 'label2':
			case 'keyword3':
			case 'label3':
			case 'keyword4':
			case 'label4':
			case 'keyword5':
			case 'label5':
				conditions.push({hasKeyword: '$label' + status.slice(-1)});
				break;
			// 'deleted': no JMAP equivalent, see method docblock
			default:
			{
				const customLabel = this.customLabelId(status);
				if (customLabel)
				{
					conditions.push({hasKeyword: '$' + customLabel.toLowerCase()});
				}
			}
		}

		return conditions.length === 1 ? conditions[0] : {operator: 'AND', conditions};
	}

	/** Resolve a custom-label id case-insensitively. */
	private customLabelId(id : string) : string | null
	{
		const labels = this.app.getCustomLabels();
		return Object.keys(labels).find(label => label.toLowerCase() === id.toLowerCase()) || null;
	}

	/**
	 * Tokenize a free-text search string the same way Mail::parseSearchTokens() does
	 * (quoted phrases as single tokens), and combine per-token OR-of-$fields conditions
	 * into a single filter tree, mirroring Mail::buildTokenizedSearch()'s and/or/+/- syntax.
	 */
	private buildTokenizedFilter(str : string, fields : string[]) : EmailFilter | null
	{
		str = (str || '').trim();
		if (!str)
		{
			return null;
		}
		const tokens : string[] = [];
		const re = /"([^"]*)"|'([^']*)'|(\S+)/gu;
		let m : RegExpExecArray;
		while ((m = re.exec(str)) !== null)
		{
			const tok = m[1] ?? m[2] ?? m[3];
			if (tok)
			{
				tokens.push(tok);
			}
		}
		if (!tokens.length)
		{
			return null;
		}

		const items : { op : 'and' | 'or', term : string, not : boolean }[] = [];
		let nextOp : 'and' | 'or' = 'or';
		for (let tok of tokens)
		{
			const lower = tok.toLowerCase();
			if (lower === 'and')
			{
				nextOp = 'and';
				continue;
			}
			if (lower === 'or')
			{
				nextOp = 'or';
				continue;
			}
			let not = false;
			if (tok.length > 1 && (tok[0] === '+' || tok[0] === '-'))
			{
				not = tok[0] === '-';
				tok = tok.substring(1);
				nextOp = 'and';
			}
			if (!tok)
			{
				continue;
			}
			items.push({op: nextOp, term: tok, not});
			nextOp = 'or';
		}
		if (!items.length)
		{
			return null;
		}

		const termFilter = (term : string, not : boolean) : EmailFilter =>
		{
			const perField = fields.map(field => ({[field]: term}));
			const cond : EmailFilter = perField.length === 1 ? perField[0] : {operator: 'OR', conditions: perField};
			return not ? {operator: 'NOT', conditions: [cond]} : cond;
		};

		let combined = termFilter(items[0].term, items[0].not);
		for (let i = 1; i < items.length; i++)
		{
			combined = {
				operator: items[i].op === 'and' ? 'AND' : 'OR',
				conditions: [combined, termFilter(items[i].term, items[i].not)],
			};
		}
		return combined;
	}

	/**
	 * Parse a size string like "5MB" the same (deliberately non-binary: MB=1024*1000, not 1024^2)
	 * way Mail::createIMAPFilter()'s LARGER/SMALLER handling does, for result parity.
	 */
	private parseSize(str : string) : number
	{
		str = str.trim();
		const m = str.match(/^([\d.]+)\s*([a-z]*)$/i);
		if (!m)
		{
			return parseFloat(str) || 0;
		}
		const mult : Record<string, number> = {
			'': 1, 'K': 1024, 'KB': 1024,
			'M': 1024 * 1000, 'MB': 1024 * 1000,
			'G': 1024 * 1000 * 1000, 'GB': 1024 * 1000 * 1000,
			'T': 1024 * 1000 * 1000 * 1000, 'TB': 1024 * 1000 * 1000 * 1000,
		};
		return parseFloat(m[1]) * (mult[m[2].toUpperCase()] ?? 1);
	}

	private toUTCDate(date : string, addDays = 0) : string
	{
		const d = new Date(date);
		if (addDays)
		{
			d.setUTCDate(d.getUTCDate() + addDays);
		}
		return d.toISOString().replace(/\.\d+Z$/, 'Z');
	}

	/**
	 * Map a JMAP Comparator property from $query.order (same values header2gridelements()'s
	 * $cols columns use).
	 */
	private buildSort(query : JmapGetRowsQuery) : { property : string, isAscending : boolean }[]
	{
		const isAscending = (query.sort || 'DESC').toUpperCase() !== 'DESC';
		let property : string;
		switch ((query.order || 'date').toLowerCase())
		{
			case 'subject':
				property = 'subject';
				break;
			case 'size':
				property = 'size';
				break;
			case 'fromaddress':
			// server picks from/to depending on folder type (sent/drafts use "to"); Phase 1
			// always sorts by "from" here, that folder-type nuance isn't ported yet
			case 'address':
				property = 'from';
				break;
			case 'toaddress':
				property = 'to';
				break;
			case 'modified':
				property = 'receivedAt';
				break;
			case 'date':
			default:
				property = 'sentAt';
				break;
		}
		return [{property, isAscending}];
	}

	/**
	 * Parse either a NextMatch row id or an egw.data UID into the JMAP ids used by this client.
	 */
	messageReference(rowId : string) : JmapMessageReference
	{
		let parts = String(rowId || '').split('::');
		if (parts[0] === 'mail')
		{
			parts = parts.slice(1);
		}
		if (parts.length !== 4 || !parts[1] || !parts[2] || !parts[3])
		{
			throw new Error(`Invalid Mail row id '${rowId}'`);
		}
		return {
			profileID: parts[1],
			mailboxId: parts[2],
			emailId: parts[3],
		};
	}

	/** Resolve an action id to its JMAP keyword. */
	private labelKeyword(labelId : string) : string
	{
		const id = labelId.toLowerCase();
		if (/^label[1-5]$/.test(id))
		{
			return '$' + id;
		}
		const customId = this.customLabelId(labelId);
		if (!customId)
		{
			throw new Error(`Unknown Mail label '${labelId}'`);
		}
		return '$' + customId.toLowerCase();
	}

	private customFlagKeyword(flagId : string) : string
	{
		const index = MailJmap.CUSTOM_FLAGS.findIndex(flag => flag.toLowerCase() === flagId.toLowerCase());
		if (index < 0)
		{
			throw new Error(`Unknown custom flag '${flagId}'`);
		}
		return '$customflag' + (index + 1);
	}

	private keywordPatch(keyword : string, set : boolean) : Record<string, boolean | null>
	{
		return {['keywords/' + keyword]: set ? true : null};
	}

	/**
	 * Submit a JMAP Email/set update, adding the local mailbox extension only for our shim.
	 *
	 * Despite the name (kept to avoid touching its many existing keyword-patch callers), also used
	 * for mailboxIds patches (moveMessages()) - the JMAP shape ({[id]: {property: value}}) is the
	 * same either way, Email/set doesn't care what the property is.
	 */
	private async updateKeywords(profileID : string, mailboxId : string,
		update : Record<string, Record<string, any>>) : Promise<any>
	{
		if (!Object.keys(update).length)
		{
			return {updated: {}};
		}
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const args : any = {accountId: token.accountId, update};
		if (token.isLocal)
		{
			args.mailboxId = mailboxId;
		}
		try
		{
			const [{result}] = await this.clients[profileID].requestMany((t) => ({
				result: t.Email.set(args),
			}));
			if (result?.notUpdated && Object.keys(result.notUpdated).length)
			{
				throw new JmapUserError(describeSetError(result.notUpdated) ?? this.egw.lang('Failed to update one or more messages'));
			}
			return result;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			console.error('MailJmap.updateKeywords(): failed', e);
			throw new JmapUserError(describeJmapError(e) ?? this.egw.lang('Unable to connect to the mail server'));
		}
	}

	private groupReferences(references : JmapMessageReference[]) : Record<string, JmapMessageReference[]>
	{
		return references.reduce((groups, reference) =>
		{
			const key = reference.profileID + '::' + reference.mailboxId;
			(groups[key] ||= []).push(reference);
			return groups;
		}, {} as Record<string, JmapMessageReference[]>);
	}

	/** Add or remove one independently stackable label. */
	async setLabel(references : JmapMessageReference[], labelId : string, set : boolean) : Promise<void>
	{
		const keyword = this.labelKeyword(labelId);
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), this.keywordPatch(keyword, set))));
	}

	/**
	 * Set the MDN-sent/not-sent keyword - a fixed keyword pair, not a dynamic label, so this
	 * bypasses labelKeyword()'s registered-label validation (same shape as setLabel() otherwise).
	 * Needs JmapShim::writableKeywords() to allow $mdnsent/$mdnnotsent (mail/src/JmapShim.php) -
	 * a real JMAP server (Stalwart) accepts any keyword already.
	 */
	async setMdnFlag(references : JmapMessageReference[], sent : boolean) : Promise<void>
	{
		const keyword = sent ? '$mdnsent' : '$mdnnotsent';
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), this.keywordPatch(keyword, true))));
	}

	/** Resolve a base (non-"un"-prefixed) action id to its JMAP keyword, or null if not one. */
	static systemFlagKeyword(actionId : string) : string | null
	{
		return MailJmap.SYSTEM_FLAG_KEYWORDS[actionId] ?? null;
	}

	/**
	 * Toggle a standard system flag - read ($seen) or flagged ($flagged) - for an explicit
	 * selection. Same shape as setMdnFlag(): bypasses labelKeyword()'s registered-label
	 * validation since these are fixed keywords, not dynamic labels. $flagged is already in
	 * JmapShim::writableKeywords(), $seen needed adding there too - a real JMAP server
	 * (Stalwart) accepts any keyword already. Scoped to explicit selections only - "select all
	 * matching filter" for these two keeps its classic, filter-aware toggle semantics (e.g.
	 * "mark all as read" while viewing the Unseen filter), not replicated here.
	 */
	async setSystemFlag(references : JmapMessageReference[], keyword : string, set : boolean) : Promise<void>
	{
		const patch = this.keywordPatch(keyword, set);
		// Unflagging clears any active colored custom flag too - a customFlag implies $flagged
		// (setCustomFlag() above), so leaving one set here would resurrect the flagged look.
		if (keyword === '$flagged' && !set)
		{
			MailJmap.CUSTOM_FLAGS.forEach(customFlag =>
				Object.assign(patch, this.keywordPatch(this.customFlagKeyword(customFlag), false)));
		}
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), patch)));
	}

	/** Remove all known labels without touching custom flags or unrelated keywords. */
	async clearLabels(references : JmapMessageReference[]) : Promise<void>
	{
		const keywords = ['$label1', '$label2', '$label3', '$label4', '$label5',
			...Object.keys(this.app.getCustomLabels()).map(id => '$' + id.toLowerCase())];
		const patch : Record<string, boolean | null> = {};
		keywords.forEach(keyword => Object.assign(patch, this.keywordPatch(keyword, false)));
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), patch)));
	}

	/** Toggle a mutually-exclusive custom flag together with the standard flagged keyword. */
	async setCustomFlag(references : JmapMessageReference[], flagId : string, set : boolean) : Promise<void>
	{
		const keyword = this.customFlagKeyword(flagId);
		const patch = this.keywordPatch(keyword, set);
		Object.assign(patch, this.keywordPatch('$flagged', set));
		if (set)
		{
			MailJmap.CUSTOM_FLAGS.forEach(other =>
			{
				const otherKeyword = this.customFlagKeyword(other);
				if (otherKeyword !== keyword)
				{
					Object.assign(patch, this.keywordPatch(otherKeyword, false));
				}
			});
		}
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), patch)));
	}

	/**
	 * Move messages to another mailbox within the SAME account, via JMAP Email/set mailboxIds -
	 * a server-internal reference change, same as the classic IMAP COPY+move path already was for
	 * same-account moves (no message bytes pass through us either way) - the win here is protocol
	 * purity for Stalwart (no IMAP connection needed for this action at all) and a uniform
	 * JMAP-shaped interface for the local shim.
	 *
	 * Cross-account moves are explicitly out of scope (see plan) - throws so the caller
	 * (app.ts) falls back to the existing server-side ajax_copyMessages(), unchanged.
	 *
	 * @param references
	 * @param targetProfileID must equal every reference's own profileID
	 * @param targetFolderPath EGroupware "/"-joined folder path of the destination, within
	 *  targetProfileID - resolved to a JMAP Mailbox id via the same cached mailboxId() lookup
	 *  getRows()/toggleForAll() already use
	 */
	async moveMessages(references : JmapMessageReference[], targetProfileID : string, targetFolderPath : string) : Promise<void>
	{
		if (!references.length || references.some(ref => ref.profileID !== targetProfileID))
		{
			throw new Error('MailJmap.moveMessages(): cross-account move not supported');
		}
		const token = await this.ensureToken(targetProfileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const targetMailboxId = await this.mailboxId(
			this.clients[targetProfileID], token.accountId, targetProfileID, targetFolderPath);
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), {mailboxIds: {[targetMailboxId]: true}})));
	}

	/**
	 * Copy messages to another mailbox within the SAME account, via JMAP Email/set's PatchObject
	 * syntax (RFC 8620 §5.3) - a partial "mailboxIds/<id>": true patch *adds* the target mailbox
	 * without touching the message's existing ones, unlike moveMessages()'s full-property
	 * replacement. Real JMAP servers (Stalwart) support this natively, no new server code needed;
	 * the local shim's emailSet() is extended to recognise this patch-path shape too.
	 *
	 * Cross-account copies are out of scope, same as moveMessages() - throws so the caller falls
	 * back to the existing server-side ajax_copyMessages().
	 */
	async copyMessages(references : JmapMessageReference[], targetProfileID : string, targetFolderPath : string) : Promise<void>
	{
		if (!references.length || references.some(ref => ref.profileID !== targetProfileID))
		{
			throw new Error('MailJmap.copyMessages(): cross-account copy not supported');
		}
		const token = await this.ensureToken(targetProfileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const targetMailboxId = await this.mailboxId(
			this.clients[targetProfileID], token.accountId, targetProfileID, targetFolderPath);
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.updateIds(group[0].profileID, group[0].mailboxId,
				group.map(reference => reference.emailId), {[`mailboxIds/${targetMailboxId}`]: true})));
	}

	/**
	 * Delete messages - always within their own account (there's no such thing as a cross-account
	 * delete). 'trash' moves them to the account's Trash mailbox (resolved via moveMessages() from
	 * the JMAP bootstrap's "trashFolder", see ensureToken()/mail_ui::ajax_jmapBootstrap()'s
	 * docblock); 'destroy' permanently removes them (JMAP Email/set destroy - the equivalent of
	 * Mail::deleteMessages()'s "remove_immediately", not "mark_as_deleted", which has no JMAP
	 * equivalent and has been removed entirely, see plan).
	 */
	async deleteMessages(references : JmapMessageReference[], mode : 'trash' | 'destroy') : Promise<void>
	{
		if (!references.length)
		{
			return;
		}
		if (mode === 'trash')
		{
			await Promise.all(Object.values(this.groupReferences(references)).map(async(group) =>
			{
				const profileID = group[0].profileID;
				const token = await this.ensureToken(profileID);
				if (!token)
				{
					throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
				}
				if (!token.trashFolder)
				{
					throw new JmapUserError(this.egw.lang('No valid %1 folder configured!', this.egw.lang('trash')));
				}
				return this.moveMessages(group, profileID, token.trashFolder);
			}));
			return;
		}
		await Promise.all(Object.values(this.groupReferences(references)).map(group =>
			this.destroyIds(group[0].profileID, group[0].mailboxId, group.map(ref => ref.emailId))));
	}

	private withKeyword(filter : EmailFilter, keyword : string, set : boolean) : EmailFilter
	{
		return {operator: 'AND', conditions: [filter, set ? {hasKeyword: keyword} : {notKeyword: keyword}]};
	}

	private async queryAllIds(client : JamClient, accountId : string, filter : EmailFilter) : Promise<string[]>
	{
		try
		{
			const ids : string[] = [];
			let total = 0;
			do
			{
				const [{page}] = await client.requestMany((t) => ({
					page: t.Email.query({
						accountId,
						filter,
						position: ids.length,
						limit: MailJmap.QUERY_PAGE_SIZE,
						calculateTotal: true,
					}),
				}));
				ids.push(...(page.ids || []));
				total = page.total ?? ids.length;
				if (!page.ids?.length)
				{
					break;
				}
			}
			while (ids.length < total);
			return ids;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			console.error('MailJmap.queryAllIds(): failed', e);
			throw new JmapUserError(describeJmapError(e) ?? this.egw.lang('Unable to connect to the mail server'));
		}
	}

	private async updateIds(profileID : string, mailboxId : string, ids : string[],
		patch : Record<string, any>) : Promise<void>
	{
		for (let start = 0; start < ids.length; start += MailJmap.QUERY_PAGE_SIZE)
		{
			const update : Record<string, Record<string, any>> = {};
			ids.slice(start, start + MailJmap.QUERY_PAGE_SIZE).forEach(id => update[id] = {...patch});
			await this.updateKeywords(profileID, mailboxId, update);
		}
	}

	/** Permanently destroy a (possibly large) set of ids, in QUERY_PAGE_SIZE-sized Email/set calls. */
	private async destroyIds(profileID : string, mailboxId : string, ids : string[]) : Promise<void>
	{
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		try
		{
			for (let start = 0; start < ids.length; start += MailJmap.QUERY_PAGE_SIZE)
			{
				const args : any = {
					accountId: token.accountId,
					destroy: ids.slice(start, start + MailJmap.QUERY_PAGE_SIZE),
				};
				if (token.isLocal)
				{
					args.mailboxId = mailboxId;
				}
				const [{result}] = await this.clients[profileID].requestMany((t) => ({
					result: t.Email.set(args) as any,
				}));
				if (result?.notDestroyed && Object.keys(result.notDestroyed).length)
				{
					throw new JmapUserError(describeSetError(result.notDestroyed) ?? this.egw.lang('Failed to delete one or more messages'));
				}
			}
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			console.error('MailJmap.destroyIds(): failed', e);
			throw new JmapUserError(describeJmapError(e) ?? this.egw.lang('Unable to connect to the mail server'));
		}
	}

	/**
	 * Move every message matching the current NextMatch filter(s) to another mailbox within the
	 * SAME account - same underlying Email/set mailboxIds patch as moveMessages(), just driven by
	 * a re-run filter query (queryAllIds()) instead of an explicit selection, mirroring
	 * toggleForAll()'s "act on everything matching the filter" shape.
	 *
	 * Cross-account moves are out of scope, same as moveMessages() - throws so the caller falls
	 * back to the existing server-side ajax_copyMessages().
	 */
	async moveAllMatching(query : JmapGetRowsQuery, targetProfileID : string, targetFolderPath : string) : Promise<void>
	{
		const [profileID, folder] = (query.selectedFolder || '').split('::', 2);
		if (profileID !== targetProfileID)
		{
			throw new Error('MailJmap.moveAllMatching(): cross-account move not supported');
		}
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const targetMailboxId = await this.mailboxId(client, token.accountId, targetProfileID, targetFolderPath);
		const ids = await this.queryAllIds(client, token.accountId, this.buildFilter(query, mailboxId));
		await this.updateIds(profileID, mailboxId, ids, {mailboxIds: {[targetMailboxId]: true}});
	}

	/**
	 * Copy every message matching the current NextMatch filter(s) to another mailbox within the
	 * SAME account - see copyMessages() for the PatchObject mechanism. Cross-account copies are out
	 * of scope, same as moveAllMatching().
	 */
	async copyAllMatching(query : JmapGetRowsQuery, targetProfileID : string, targetFolderPath : string) : Promise<void>
	{
		const [profileID, folder] = (query.selectedFolder || '').split('::', 2);
		if (profileID !== targetProfileID)
		{
			throw new Error('MailJmap.copyAllMatching(): cross-account copy not supported');
		}
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const targetMailboxId = await this.mailboxId(client, token.accountId, targetProfileID, targetFolderPath);
		const ids = await this.queryAllIds(client, token.accountId, this.buildFilter(query, mailboxId));
		await this.updateIds(profileID, mailboxId, ids, {[`mailboxIds/${targetMailboxId}`]: true});
	}

	/**
	 * Delete every message matching the current NextMatch filter(s) - 'trash' delegates to
	 * moveAllMatching() (same trashFolder resolution deleteMessages() uses), 'destroy' permanently
	 * removes them. See deleteMessages() for the single-selection equivalent.
	 */
	async deleteAllMatching(query : JmapGetRowsQuery, mode : 'trash' | 'destroy') : Promise<void>
	{
		const [profileID, folder] = (query.selectedFolder || '').split('::', 2);
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		if (mode === 'trash')
		{
			if (!token.trashFolder)
			{
				throw new JmapUserError(this.egw.lang('No valid %1 folder configured!', this.egw.lang('trash')));
			}
			await this.moveAllMatching(query, profileID, token.trashFolder);
			return;
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const ids = await this.queryAllIds(client, token.accountId, this.buildFilter(query, mailboxId));
		await this.destroyIds(profileID, mailboxId, ids);
	}

	/**
	 * Permanently destroy every message in the account's Trash or Junk mailbox - "empty trash"/
	 * "empty junk". Reuses deleteAllMatching() with a query that has no search/filter/dates, just
	 * a selectedFolder - buildFilter() then produces a filter of just "inMailbox", i.e. everything
	 * in that one folder, minus whatever JMAP already never exposes (\Deleted-flagged messages).
	 *
	 * @param which 'trash' or 'junk' - resolved via the JMAP bootstrap's trashFolder/junkFolder
	 *  (see ajax_jmapBootstrap()'s docblock); throws (caller falls back to the classic
	 *  ajax_emptyTrash()/ajax_emptySpam() call) if the profile has no such folder configured
	 * @return the resolved "profileID::folder" selectedFolder key that was purged, so the caller
	 *  can update the folder-tree badge / refresh the grid without a server round trip (the classic
	 *  ajax call's response used to push these via app.mail.setFolderStatus/egw.refresh)
	 */
	async purgeFolder(profileID : string, which : 'trash' | 'junk') : Promise<string>
	{
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const folder = which === 'trash' ? token.trashFolder : token.junkFolder;
		if (!folder)
		{
			throw new JmapUserError(this.egw.lang('No valid %1 folder configured!', this.egw.lang(which)));
		}
		const selectedFolder = profileID + '::' + folder;
		await this.deleteAllMatching({selectedFolder}, 'destroy');
		return selectedFolder;
	}

	/** Toggle a label or custom flag for every message matching the current NextMatch filters. */
	async toggleForAll(query : JmapGetRowsQuery, actionId : string) : Promise<void>
	{
		const [profileID, folder] = (query.selectedFolder || '').split('::', 2);
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const filter = this.buildFilter(query, mailboxId);
		const customFlag = MailJmap.CUSTOM_FLAGS.includes(actionId);
		const keyword = customFlag ? this.customFlagKeyword(actionId) : this.labelKeyword(actionId);
		const [setIds, removeIds] = await Promise.all([
			this.queryAllIds(client, token.accountId, this.withKeyword(filter, keyword, false)),
			this.queryAllIds(client, token.accountId, this.withKeyword(filter, keyword, true)),
		]);

		const setPatch = this.keywordPatch(keyword, true);
		if (customFlag)
		{
			Object.assign(setPatch, this.keywordPatch('$flagged', true));
			MailJmap.CUSTOM_FLAGS.forEach(other =>
			{
				const otherKeyword = this.customFlagKeyword(other);
				if (otherKeyword !== keyword)
				{
					Object.assign(setPatch, this.keywordPatch(otherKeyword, false));
				}
			});
		}
		const removePatch = this.keywordPatch(keyword, false);
		if (customFlag)
		{
			Object.assign(removePatch, this.keywordPatch('$flagged', false));
		}
		await this.updateIds(profileID, mailboxId, setIds, setPatch);
		await this.updateIds(profileID, mailboxId, removeIds, removePatch);
	}

	/** Clear all labels from every message matching the current NextMatch filters. */
	async clearLabelsForAll(query : JmapGetRowsQuery) : Promise<void>
	{
		const [profileID, folder] = (query.selectedFolder || '').split('::', 2);
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new JmapUserError(this.egw.lang('Unable to connect to the mail server'));
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const ids = await this.queryAllIds(client, token.accountId, this.buildFilter(query, mailboxId));
		const patch : Record<string, boolean | null> = {};
		['$label1', '$label2', '$label3', '$label4', '$label5',
			...Object.keys(this.app.getCustomLabels()).map(id => '$' + id.toLowerCase())]
			.forEach(keyword => Object.assign(patch, this.keywordPatch(keyword, false)));
		await this.updateIds(profileID, mailboxId, ids, patch);
	}

	/**
	 * Map a JMAP Email object to a row shaped like mail_ui::header2gridelements()'s output
	 *
	 * Simplified for Phase 1: no smime-type detection, no attachment-list preview block,
	 * no X-Priority header. row_id uses mail_ui::generateJmapRowID()'s scheme so legacy
	 * server-side actions can recognise and decode it when needed.
	 */
	/**
	 * Convert a JMAP UTCDate (RFC 8621 - always true UTC, both backends: real JMAP and
	 * JmapShim::imapDate() alike) into eTemplate/get_rows()'s date convention: digits shown as
	 * the *user's* configured timezone, with a literal (not real) "Z" suffix so the browser
	 * displays those wall-clock numbers as-is instead of re-applying its own browser-local
	 * conversion on top.
	 */
	private jmapUtcToUserTz(iso : string) : string
	{
		if (!iso) return iso;
		const tz = this.egw.preference('tz', 'common') || 'UTC';
		const fmt = new Intl.DateTimeFormat('en-US', {
			timeZone: tz, hour12: false,
			year: 'numeric', month: '2-digit', day: '2-digit',
			hour: '2-digit', minute: '2-digit', second: '2-digit',
		});
		const parts : Record<string, string> = {};
		fmt.formatToParts(new Date(iso)).forEach(p => parts[p.type] = p.value);
		// hour12:false can still yield "24" for midnight in some engines - normalize to "00"
		if (parts.hour === '24') parts.hour = '00';
		return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}Z`;
	}

	/**
	 * Turn a JMAP Email's `keywords` map into the flags/css-classes/status-icon shape both
	 * email2row() and emails2threadRow() (doc/ai/projects/mail-threaded-view.md, Phase 1) render
	 * rows from - extracted out of email2row() so the threaded-row aggregate (built from a
	 * synthetic, OR/AND-folded `keywords` map spanning every message in a thread, see
	 * aggregateThreadKeywords()) gets pixel-identical rendering to a normal single-message row.
	 */
	private keywordsToRowFlags(keywords : Record<string, boolean>) :
		{ flags : Record<string, string>, css : string[], status_icon : string, hasFlagged : boolean }
	{
		const flags : Record<string, string> = {};
		const css = ['mail'];
		if (keywords['$flagged'])
		{
			flags.flagged = 'flagged';
			css.push('flagged');
		}
		if (keywords['$answered'])
		{
			flags.replied = 'replied';
			css.push('replied');
		}
		if (keywords['$forwarded'])
		{
			flags.forwarded = 'forwarded';
			css.push('forwarded');
		}
		if (keywords['$seen'])
		{
			flags.read = 'read';
		}
		else
		{
			css.push('unseen');
		}
		if (keywords['$mdnsent'])
		{
			flags.mdnsent = 'mdnsent';
		}
		if (keywords['$mdnnotsent'])
		{
			flags.mdnnotsent = 'mdnnotsent';
		}
		for (let i = 1; i <= 5; i++)
		{
			if (keywords['$label' + i])
			{
				flags['label' + i] = 'label' + i;
				css.push('label' + i);
			}
		}
		Object.keys(this.app.getCustomLabels()).forEach(labelId =>
		{
			if (keywords['$' + labelId.toLowerCase()])
			{
				flags[labelId] = labelId;
				css.push(labelId);
			}
		});
		MailJmap.CUSTOM_FLAGS.forEach((customFlag, index) =>
		{
			if (keywords['$customflag' + (index + 1)])
			{
				flags[customFlag] = customFlag;
				css.push(customFlag);
			}
		});

		let status_icon = '';
		if (keywords['$forwarded']) status_icon = 'mail_forward';
		else if (keywords['$answered']) status_icon = 'mail_reply';
		else if (!keywords['$seen']) status_icon = 'mail_unseen';

		const hasFlagged = keywords['$flagged'] || MailJmap.CUSTOM_FLAGS.some((flag, index) =>
			keywords['$customflag' + (index + 1)]);

		return {flags, css, status_icon, hasFlagged};
	}

	/**
	 * Fold every member message's `keywords` map of a collapsed thread into one synthetic map,
	 * so a closed thread row renders exactly like a single email whose state is the aggregate of
	 * its members (doc/ai/projects/mail-threaded-view.md, "Unseen (and other) rollup" section).
	 *
	 * $seen is AND-folded (the thread only looks "read" once every member is) - every other
	 * keyword this class renders ($flagged/$answered/$forwarded/labels/custom flags) is OR-folded
	 * (any member having it is enough to show it on the closed thread row). MDN keywords are
	 * intentionally left off the aggregate: they're a per-message reply-tracking state, not
	 * something that means anything folded across a whole thread.
	 */
	private aggregateThreadKeywords(members : { keywords? : Record<string, boolean> }[]) : Record<string, boolean>
	{
		const aggregate : Record<string, boolean> = {
			'$seen': members.every(m => !!(m.keywords || {})['$seen']),
		};
		const orKeys = new Set<string>();
		members.forEach(m => Object.keys(m.keywords || {}).forEach(k => k !== '$seen' && orKeys.add(k)));
		orKeys.forEach(key =>
		{
			aggregate[key] = members.some(m => !!(m.keywords || {})[key]);
		});
		return aggregate;
	}

	/**
	 * Build the closed/collapsed row for a JMAP thread with more than one member message
	 * (doc/ai/projects/mail-threaded-view.md, Phase 1) - a single-message "thread" just uses the
	 * ordinary email2row() instead, so the common case (most threads, most inboxes) renders
	 * identically to today's flat list.
	 *
	 * @param representative the Email JMAP chose to represent the collapsed thread (Email/query's
	 *  own collapseThreads semantics - RFC 8621 ties this to sort order, not "most recent")
	 * @param members every message in the thread (from Thread/get's emailIds), each with at least
	 *  {id, keywords} - used only for the aggregate rollup, not for display fields
	 */
	private emails2threadRow(representative : any, members : { id : string, keywords? : Record<string, boolean> }[],
		threadId : string, profileID : string, mailboxId : string) : any
	{
		const row = this.email2row(representative, profileID, mailboxId);
		const {flags, css, status_icon, hasFlagged} = this.keywordsToRowFlags(this.aggregateThreadKeywords(members));
		return {
			...row,
			// the representative's own row_id/uid still end in its plain email id (from
			// email2row() above) - replace just that trailing segment so every other piece of
			// code that parses row_id as "account_id::profileID::mailboxId::X" keeps working
			// unchanged, with X now identifying a thread instead of a single message
			row_id: row.row_id.slice(0, row.row_id.lastIndexOf('::') + 2) + 'thread:' + threadId,
			uid: 'thread:' + threadId,
			is_parent: true,
			thread_id: threadId,
			thread_count: members.length,
			flags,
			class: css.join(' '),
			status_icon,
			flagged_icon: hasFlagged ? 'unread_flagged_small' : '',
		};
	}

	private email2row(email : any, profileID : string, mailboxId : string) : any
	{
		const addressList = (list : { name? : string, email : string }[]) =>
			(list || []).map(a => a.name ? `${a.name} <${a.email}>` : a.email);
		// a real JMAP server (eg. Stalwart) parses From/To/Cc/Bcc itself - if its own parser
		// isn't RFC 2047-aware, a sending MUA's malformed encoded-word (a literal, unencoded
		// comma inside a quoted display name - valid per RFC 2047, but breaks a naive
		// comma-split) trips it up in one of two ways seen so far: either the address boundary
		// itself gets split wrong (an entry ends up with no usable email at all), or the
		// boundary is found correctly but the display-name decode leaves stray backslashes/
		// quotes behind (a literal "\" never legitimately appears in a decoded display name).
		// The local IMAP shim never has this problem (JmapShim::addressListFromHeader() already
		// re-parses raw headers unconditionally), so this only ever fires for a real server's
		// own mistake.
		const suspectFields = (['from', 'to', 'cc', 'bcc'] as const).filter(field =>
			(email[field] || []).some((a : { name? : string, email? : string }) =>
				!a.email || a.email.indexOf('@') < 0 || (a.name && a.name.indexOf('\\') >= 0)));

		const keywords : Record<string, boolean> = email.keywords || {};
		const {flags, css, status_icon, hasFlagged} = this.keywordsToRowFlags(keywords);

		// mail_ui::header2gridelements()'s convention (relied on by app.ts's preview(), which
		// concats "primary address" + "additional addresses" into one list for the preview panel):
		// toaddress/fromaddress hold only the *first* address as a single string, any further
		// recipients go in additionaltoaddress/additionalfromaddress as one string each. cc/bcc
		// have no such split - there's no single-value list column for them, so every recipient
		// goes in ccaddress/bccaddress as one string each.
		const fromList = addressList(email.from);
		const toList = addressList(email.to);

		return {
			row_id: this.app.egw.user('account_id') + '::' + profileID + '::' + mailboxId + '::' + email.id,
			uid: email.id,
			subject: email.subject || '(' + this.egw.lang('no subject') + ')',
			fromaddress: fromList[0] || '',
			additionalfromaddress: fromList.slice(1),
			toaddress: toList[0] || '',
			additionaltoaddress: toList.slice(1),
			ccaddress: addressList(email.cc),
			bccaddress: addressList(email.bcc),
			address: fromList[0] || '',
			date: this.jmapUtcToUserTz(email.sentAt || email.receivedAt),
			modified: this.jmapUtcToUserTz(email.receivedAt),
			size: email.size,
			bodypreview: email.preview || '',
			// MDN (read-receipt) prompt trigger - preview() (app.ts) checks this against the
			// mdnsent/mdnnotsent keywords below to decide whether to show the Yes/No dialog
			dispositionnotificationto: email[MailJmap.MDN_HEADER_PROPERTY] || '',
			// Kept for the preview's attachment-presence check.  Row templates use
			// the individual image values below instead of a legacy html widget.
			attachments: email.hasAttachment ? 'attach' : '',
			attachment_icon: email.hasAttachment ? 'attach' : '',
			flagged_icon: hasFlagged ? 'unread_flagged_small' : '',
			// no attachment-list preview block for Phase 1 (see class docblock) - but app.ts's
			// preview() unconditionally reads data.attachmentsBlock[0], so this must at
			// least exist as an array or clicking a row throws and the preview never loads
			attachmentsBlock: [],
			class: css.join(' '),
			icon: 'bug-fill',
			flags,
			status_icon,
			emailTag: this.egw.preference('emailTag', 'mail') || 'onlyname',
			// non-empty only for the rare broken-server case above - MailApp.renderMessageInto()
			// (mail/js/app.ts) checks this to trigger repairAddressField() on demand, the same
			// way it already does for attachmentsBlock
			suspectAddressFields: suspectFields,
		};
	}

	/**
	 * On-demand repair for a From/To/Cc/Bcc field whose JMAP-provided address list looks broken
	 * (an entry with no usable email address, or a valid one with a mangled display name) - see
	 * email2row()'s suspectAddressFields and mail_ui::ajax_parseAddressList()'s docblock for the
	 * full story. Fetches the raw header text directly from the JMAP server (header:<field>,
	 * RFC 8621 4.1.3's default "Raw" form) and re-parses it server-side via the same
	 * Api\Mail::parseAddressList() the classic pre-JMAP code has always used.
	 *
	 * Note this can only repair what the server's raw header actually still contains - if the
	 * server itself already rewrote/re-encoded the header when storing the message (seen live
	 * against Stalwart for one particularly malformed sender), the "raw" value is that rewrite,
	 * not the true original wire bytes, and this can only do the best possible reconstruction
	 * from what's left (Api\Mail::parseAddressList()'s repair heuristic still meaningfully
	 * improves such cases even then, just not to 100% original fidelity).
	 *
	 * @param rowId
	 * @param field 'from'|'to'|'cc'|'bcc'
	 * @return null on any failure or if the server has nothing for that raw-header property -
	 *  caller keeps whatever (broken) list it already had rather than showing nothing
	 */
	async repairAddressField(rowId : string, field : 'from' | 'to' | 'cc' | 'bcc') : Promise<{ name? : string, email : string }[] | null>
	{
		try
		{
			const ref = this.messageReference(rowId);
			const token = await this.ensureToken(ref.profileID);
			if (!token)
			{
				return null;
			}
			// no explicit ":asRaw" suffix - RFC 8621 4.1.3 defaults an unqualified "header:name"
			// property to the Raw form already, and Stalwart echoes the property key back
			// canonicalized to this default form regardless of which explicit suffix was
			// requested - requesting the bare form here means the response key always matches
			// what we're looking for, on Stalwart and any other spec-compliant server alike.
			const property = `header:${field}`;
			const args : any = {
				accountId: token.accountId,
				ids: [ref.emailId],
				properties: [property],
			};
			if (token.isLocal)
			{
				args.mailboxId = ref.mailboxId;
			}
			const [{emails}] = await this.clients[ref.profileID].requestMany((t) => ({
				emails: t.Email.get(args) as any,
			}));
			const raw = (emails.list || [])[0] && (emails.list || [])[0][property];
			if (!raw)
			{
				return null;
			}
			return await this.egw.request('mail.mail_ui.ajax_parseAddressList', [raw]);
		}
		catch (e)
		{
			console.error('MailJmap.repairAddressField(): failed', e);
			return null;
		}
	}
}
