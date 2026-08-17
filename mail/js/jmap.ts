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
import DOMPurify from "../../api/js/etemplate/Et2Image/dompurify-shim";

interface JmapToken
{
	sessionUrl : string;
	accountId : string;
	access_token : string;
	expires_at : number;	// ms epoch, with our own safety-margin already subtracted
	isLocal : boolean;
	customLabels : Record<string, {name : string, color : string, icon? : string}>;
	trashFolder? : string;	// EGroupware "/"-joined folder path, e.g. "Trash" - see deleteMessages()
	junkFolder? : string;	// EGroupware "/"-joined folder path, e.g. "Junk" - null if not configured
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
	private clients : Record<string, JamClient> = {};
	private refreshTimers : Record<string, number> = {};
	private ineligibleUntil : Record<string, number> = {};
	// whether ajax_enablePush has already been (fire-and-forget) triggered for the profile's
	// current token - reset whenever ensureToken() obtains a fresh token, so the mail server's
	// push subscription/token still gets renewed well within its expiry without doing so on
	// every single row fetch (see fetchRows())
	private pushEnabled : Record<string, boolean> = {};
	// "profileID::folder/path" -> JMAP Mailbox id
	private mailboxIds : Record<string, string> = {};
	private static readonly CUSTOM_FLAGS = ['customFlag1', 'customFlag2', 'customFlag3', 'customFlag4', 'customFlag5'];
	// standard (non-label, non-custom-flag) system flags the UI can bulk-toggle for an explicit
	// selection - keyed by the base ("un"-stripped) action id used throughout mail/js/app.ts
	private static readonly SYSTEM_FLAG_KEYWORDS : Record<string, string> = {read: '$seen', flagged: '$flagged'};
	private static readonly QUERY_PAGE_SIZE = 500;
	// RFC 8621 §4.1.3 header-property name for the MDN (read-receipt) prompt - matches
	// JmapShim::MDN_HEADER_PROPERTY (mail/src/JmapShim.php), which echoes this same key back for
	// local-shim accounts; a real JMAP server (Stalwart) does so natively per spec
	private static readonly MDN_HEADER_PROPERTY = 'header:disposition-notification-to:asText';

	get egw() : IegwAppLocal
	{
		return this.app.egw;
	}

	constructor(app : MailApp)
	{
		this.app = app;
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
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);

		const start = query.start || 0;
		const limit = query.num_rows || 50;
		// matches mail_ui::get_rows()'s "fetchPreview" behaviour: the (comparatively expensive)
		// message-body preview snippet is only fetched when the "Sneak preview in list" toggle
		// (filter2 / mail.ShowDetails preference) is on
		const fetchPreview = !!query.filter2;

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
	 * Fetch one level's worth of a mailbox's direct children - lazy per-level folder-tree
	 * loading (see doc/ai/projects/mail-folder-tree-jmap.md), the JMAP counterpart of the
	 * classic ajax_foldertree/ajax_tree_autoloading per-level IMAP LIST. Batches Mailbox/query
	 * (list children of parentId) + Mailbox/get (full node data for those ids) in one request,
	 * the same result-reference pattern getRows() already uses for Email/query+Email/get.
	 *
	 * Never throws for "this account isn't reachable right now" - returns null so the caller
	 * (mail_ui's Et2Tree instance, via its now-callback-capable `autoloading`) can fall back to
	 * the classic ajax_foldertree fetch for that one node.
	 *
	 * @param profileID
	 * @param parentId JMAP Mailbox id of the parent, or null for the top level
	 * @return null if this account has no usable JMAP access-token (server unreachable, MFA, ...)
	 */
	async getMailboxChildren(profileID : string, parentId : string | null) : Promise<any[] | null>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token)
			{
				return null;
			}
			const client = this.clients[profileID];

			const [{mailboxes}] = await client.requestMany((t) =>
			{
				const ids = t.Mailbox.query({
					accountId: token.accountId,
					filter: {parentId},
				});
				const mailboxes = t.Mailbox.get({
					accountId: token.accountId,
					ids: ids.$ref('/ids'),
				});
				return {mailboxes};
			});
			return mailboxes.list || [];
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
			console.error('MailJmap.getMailboxChildren(): failed, falling back to the classic ajax_foldertree fetch', e);
			return null;
		}
	}

	/**
	 * Fetch *every* mailbox in the account in one call - unlike getMailboxChildren()'s
	 * lazy per-level fetch (the right choice for browsing an account with hundreds of folders),
	 * the subscribe-management popup (mail.subscribe, mail_ui::subscription()) genuinely needs
	 * the whole tree at once, since the user toggles subscriptions across the entire account in
	 * one multi-select tree before saving. Uses `ids: null` (RFC 8620 "all"), the same
	 * mailboxGet() mode kept (but not used as the primary path) since Phase 1 of the folder-tree
	 * migration for exactly this future use.
	 *
	 * @param profileID
	 * @return null if this account has no usable JMAP access-token (server unreachable, MFA, ...)
	 */
	async getMailboxTree(profileID : string) : Promise<any[] | null>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return null;
			const client = this.clients[profileID];

			const [{mailboxes}] = await client.requestMany((t) => ({
				mailboxes: t.Mailbox.get({accountId: token.accountId, ids: null}),
			}));
			return mailboxes.list || [];
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.getMailboxTree(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.getMailboxTree(): failed, falling back to the classic server-rendered tree', e);
			return null;
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
	 * mail_refreshFolderLevel(), which needs a parent's JMAP id to re-fetch its children after a
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
	 * Create a new mailbox - the JMAP fast path for mail_AddFolder() (mail/js/app.ts), falling
	 * back to the classic ajax_addFolder on failure/ineligibility.
	 *
	 * @param profileID
	 * @param parentPath canonical path of the parent folder, '' for the top level
	 * @param name new folder's (leaf) name
	 * @return false on any failure (never throws) - the caller falls back to classic
	 */
	async createMailbox(profileID : string, parentPath : string, name : string) : Promise<boolean>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return false;
			const client = this.clients[profileID];
			const parentId = await this.mailboxIdOrNull(client, token.accountId, profileID, parentPath);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, create: {c0: {name, parentId}}}),
			}));
			const success = !!(result.created && result.created['c0']);
			if (!success)
			{
				const message = describeSetError(result.notCreated);
				if (message) throw new JmapUserError(message);
			}
			return success;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.createMailbox(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.createMailbox(): failed, falling back to the classic ajax_addFolder', e);
			return false;
		}
	}

	/**
	 * Rename a mailbox in place (same parent) - the JMAP fast path for mail_RenameFolder().
	 *
	 * @param profileID
	 * @param path canonical path of the folder to rename
	 * @param newName
	 */
	async renameMailbox(profileID : string, path : string, newName : string) : Promise<boolean>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return false;
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, update: {[id]: {name: newName}}}),
			}));
			const success = !!(result.updated && Object.prototype.hasOwnProperty.call(result.updated, id));
			if (success)
			{
				this.invalidateMailboxIdCache(profileID, path);
			}
			else
			{
				const message = describeSetError(result.notUpdated);
				if (message) throw new JmapUserError(message);
			}
			return success;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.renameMailbox(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.renameMailbox(): failed, falling back to the classic ajax_renameFolder', e);
			return false;
		}
	}

	/**
	 * Move a mailbox to a new parent - the JMAP fast path for mail_MoveFolder(). Same-account only
	 * (mail_MoveFolder() already rejects a cross-account move before ever calling this).
	 *
	 * @param profileID
	 * @param path canonical path of the folder to move
	 * @param newParentPath canonical path of the new parent, '' for the top level
	 */
	async moveMailbox(profileID : string, path : string, newParentPath : string) : Promise<boolean>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return false;
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);
			const newParentId = await this.mailboxIdOrNull(client, token.accountId, profileID, newParentPath);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, update: {[id]: {parentId: newParentId}}}),
			}));
			const success = !!(result.updated && Object.prototype.hasOwnProperty.call(result.updated, id));
			if (success)
			{
				this.invalidateMailboxIdCache(profileID, path);
			}
			else
			{
				const message = describeSetError(result.notUpdated);
				if (message) throw new JmapUserError(message);
			}
			return success;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.moveMailbox(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.moveMailbox(): failed, falling back to the classic ajax_MoveFolder', e);
			return false;
		}
	}

	/**
	 * Delete a mailbox - the JMAP fast path for mail_DeleteFolder().
	 *
	 * @param profileID
	 * @param path canonical path of the folder to delete
	 */
	async deleteMailbox(profileID : string, path : string) : Promise<boolean>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return false;
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, destroy: [id]}),
			}));
			const success = Array.isArray(result.destroyed) && result.destroyed.includes(id);
			if (success)
			{
				this.invalidateMailboxIdCache(profileID, path);
			}
			else
			{
				const message = describeSetError(result.notDestroyed);
				if (message) throw new JmapUserError(message);
			}
			return success;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.deleteMailbox(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.deleteMailbox(): failed, falling back to the classic ajax_deleteFolder', e);
			return false;
		}
	}

	/**
	 * (Un)subscribe a mailbox - the JMAP fast path for subscribe_folder()/unsubscribe_folder().
	 *
	 * @param profileID
	 * @param path canonical path of the folder
	 * @param subscribed
	 */
	async setMailboxSubscribed(profileID : string, path : string, subscribed : boolean) : Promise<boolean>
	{
		try
		{
			const token = await this.ensureToken(profileID);
			if (!token) return false;
			const client = this.clients[profileID];
			const id = await this.mailboxId(client, token.accountId, profileID, path);

			const [{result}] = await client.requestMany((t) => ({
				result: t.Mailbox.set({accountId: token.accountId, update: {[id]: {isSubscribed: subscribed}}}),
			}));
			const success = !!(result.updated && Object.prototype.hasOwnProperty.call(result.updated, id));
			if (!success)
			{
				const message = describeSetError(result.notUpdated);
				if (message) throw new JmapUserError(message);
			}
			return success;
		}
		catch (e)
		{
			if (e instanceof JmapUserError) throw e;
			const message = describeJmapError(e);
			if (message)
			{
				console.error('MailJmap.setMailboxSubscribed(): JMAP error', e);
				throw new JmapUserError(message);
			}
			console.error('MailJmap.setMailboxSubscribed(): failed, falling back to the classic ajax_foldersubscription', e);
			return false;
		}
	}

	/**
	 * egw.dataRegisterFetch('mail', ...) callback: the only way NextMatch's row-fetch gets
	 * answered - there is no server-side row-fetch fallback (see class docblock).
	 *
	 * Falls back (resolves false) for parent/children (csv_export, unused by mail), or if
	 * `selectedFolder` genuinely can't be determined yet (see below) - egw_data.js's dataFetch()
	 * then still POSTs to ajax_get_rows, but mail no longer registers a 'get_rows' callback, so
	 * that resolves as an empty result rather than real rows. Never rejects.
	 *
	 * @param _execId unused
	 * @param _queriedRange {start, num_rows} or {refresh: [...]}
	 * @param _filters raw NextMatch activeFilters, incl. selectedFolder and sort as {id, asc}
	 * @param _widgetId unused
	 * @param _knownUids unused - JMAP always tells us the true total via calculateTotal
	 * @param _lastModification unused - we don't support incremental/only-changed fetches yet
	 */
	fetchRows(_execId : string, _queriedRange : any, _filters : any, _widgetId : string,
		_knownUids : string[], _lastModification : number) : false | Promise<any>
	{
		if (_queriedRange.refresh)
		{
			return this.refreshRows(typeof _queriedRange.refresh === 'string' ?
				[_queriedRange.refresh] : _queriedRange.refresh, !!_filters.filter2);
		}
		if (_queriedRange.parent_id)
		{
			return false;
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
		// for this one-time startup race, since a real folder click follows immediately after.
		let selectedFolder = _filters.selectedFolder ||
			this.app.et2?.getWidgetById(this.app.nm_index + '[foldertree]')?.getValue() ||
			this.egw.preference('ActiveProfileID', 'mail');
		if (!selectedFolder)
		{
			return false;
		}
		// egw.preference() auto-casts a purely-numeric stored value (just "42", no "::folder"
		// suffix yet - happens right after switching account, before a folder click persists
		// one) to a real JS number, not a string - guard the .match() below against that.
		selectedFolder = String(selectedFolder);
		if (!selectedFolder.match(/::/))
		{
			selectedFolder += '::INBOX';
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
		};
		// sort is only split into order/sort strings server-side (Nextmatch.php), still a
		// {id, asc} object at this point - same normalisation buildSort() expects as input
		if (_filters.sort && typeof _filters.sort === 'object')
		{
			query.order = _filters.sort.id;
			query.sort = _filters.sort.asc ? 'ASC' : 'DESC';
		}

		return this.getRows(query).then((result) : any =>
		{
			if (!result)
			{
				return false;
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
		}).catch((e) =>
		{
			const message = e instanceof JmapUserError ? e.message : describeJmapError(e);
			if (message) this.egw.message(message, 'error');
			console.error('MailJmap.fetchRows(): failed, resolving as an empty result', e);
			return false;
		});
	}

	/**
	 * Fire-and-forget (re-)enable server push for $selectedFolder's account, at most once per
	 * profile per JMAP-token lifetime (reset by ensureToken() whenever it gets a fresh token -
	 * comfortably more often than the mail server's own push subscription/token needs renewing,
	 * without doing so on every single row fetch like the old server-side get_rows() did).
	 *
	 * Covers both push mechanisms Api\Mail\Imap\PushIface implementors may use: Stalwart's native
	 * JMAP push subscriptions, and plain IMAP/Dovecot's mailbox-metadata push token registration
	 * (opt-in via the "imap_hosts_with_push" site config) - mail_ui::ajax_enablePush() itself just
	 * calls whichever the account's actual server class implements.
	 */
	private enablePushOnce(selectedFolder : string) : void
	{
		const profileID = selectedFolder.split('::', 1)[0];
		if (this.pushEnabled[profileID])
		{
			return;
		}
		this.pushEnabled[profileID] = true;
		this.egw.request('mail.mail_ui.ajax_enablePush', [profileID, selectedFolder]).catch((e) =>
		{
			console.error('MailJmap.enablePushOnce(): failed', e);
			delete this.pushEnabled[profileID];
		});
	}

	/**
	 * Handle NextMatch's single/multi-row refresh fetch (egw.dataRefreshUID(), fired after row
	 * actions like flag/delete and by push "update" events) directly via JMAP Email/get.
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
	private async refreshRows(rowIds : string[], fetchPreview : boolean) : Promise<false | any>
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
					console.warn('MailJmap.refreshRows(): dropping malformed row id', rowId);
				}
			}
			if (!references.length)
			{
				return false;
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
					throw new Error(`MailJmap.refreshRows(): profile ${profileID} is not JMAP-eligible`);
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
			if (message) this.egw.message(message, 'error');
			console.error('MailJmap.refreshRows(): failed, resolving as an empty result', e);
			return false;
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
	 * Fetch and assemble one message's body directly via JMAP (Stalwart, or the local IMAP shim)
	 *
	 * Fetches bodyStructure/textBody/htmlBody/attachments/bodyValues in one optimistic round trip
	 * (fetchAllBodyValues) and inspects the returned structure for the special cases above -
	 * discarding the fetched bodyValues and returning {special:true} if found, so the caller
	 * (app.ts) falls back to the existing server-rendered iframe unchanged. Never throws - any
	 * failure (unreachable profile, network error) also resolves {special:true}, for the same
	 * fallback reason.
	 */
	async fetchBody(rowId : string, htmlOptions? : string) : Promise<JmapBodyResult>
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
			const [{emails}] = await this.clients[ref.profileID].requestMany((t) => ({
				emails: t.Email.get(args) as any,
			}));
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
	 * cid: image references are left as-is here - resolved asynchronously after render by
	 * resolveInlineImages(), same as external images already are (resolveExternalImages()).
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
		}
		else
		{
			body = MailJmap.textToHtml(raw);
		}
		return this.wrapDocument(body);
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

		const images = Array.from(doc.querySelectorAll('img[src^="cid:"]')) as HTMLImageElement[];
		if (!images.length)
		{
			return;
		}
		const byCid : Record<string, any> = {};
		result.attachments.forEach((att : any) =>
		{
			if (att.cid)
			{
				byCid[att.cid] = att;
			}
		});

		await Promise.all(images.map(async(img) =>
		{
			const cid = decodeURIComponent(img.getAttribute('src').substring(4));
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
	 * @throws Error on any failure - caller falls back to the classic getAttachment() URL
	 */
	async downloadAttachment(profileID : string, blobId : string, filename : string, mimeType : string) : Promise<void>
	{
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
		}
		const response = await this.clients[profileID].downloadBlob({
			accountId: token.accountId,
			blobId,
			mimeType: mimeType || 'application/octet-stream',
			fileName: filename || 'attachment',
		});
		const url = URL.createObjectURL(await response.blob());
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
	 * @throws Error on any failure - caller falls back to the classic displayHeader popup
	 */
	async fetchRawHeader(rowId : string) : Promise<string>
	{
		const reference = this.messageReference(rowId);
		const token = await this.ensureToken(reference.profileID);
		if (!token)
		{
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
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
			throw new Error('Unable to resolve the message blobId');
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
						customLabels: data.customLabels || {},
						trashFolder: data.trashFolder,
						junkFolder: data.junkFolder,
					};
					if (Object.keys(token.customLabels).length)
					{
						this.app.customLabels = token.customLabels;
						this.app.mail_updateCustomLabelStylesheet();
					}
					this.tokens[profileID] = token;
					this.clients[profileID] = new JamClient({
						sessionUrl: token.sessionUrl,
						bearerToken: token.access_token,
					});
					// fresh token: renew the mail server's push subscription/token again too,
					// next time we know which folder is being viewed (see fetchRows())
					delete this.pushEnabled[profileID];
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
				throw new Error(`MailJmap: folder '${folderPath}' not found`);
			}
			parentId = id;
		}
		return this.mailboxIds[cacheKey] = id;
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
		const labels = this.app.mail_getCustomLabels();
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
		}
		const args : any = {accountId: token.accountId, update};
		if (token.isLocal)
		{
			args.mailboxId = mailboxId;
		}
		const [{result}] = await this.clients[profileID].requestMany((t) => ({
			result: t.Email.set(args),
		}));
		if (result?.notUpdated && Object.keys(result.notUpdated).length)
		{
			throw new JmapUserError(describeSetError(result.notUpdated) ?? this.egw.lang('Failed to update one or more messages'));
		}
		return result;
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
			...Object.keys(this.app.mail_getCustomLabels()).map(id => '$' + id.toLowerCase())];
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
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
					throw new Error(this.egw.lang('Unable to connect to the mail server'));
				}
				if (!token.trashFolder)
				{
					throw new Error('MailJmap.deleteMessages(): no trash folder known for this profile');
				}
				return this.moveMessages(group, profileID, token.trashFolder);
			}));
			return;
		}
		await Promise.all(Object.values(this.groupReferences(references)).map(async(group) =>
		{
			const profileID = group[0].profileID;
			const token = await this.ensureToken(profileID);
			if (!token)
			{
				throw new Error(this.egw.lang('Unable to connect to the mail server'));
			}
			const args : any = {
				accountId: token.accountId,
				destroy: group.map(ref => ref.emailId),
			};
			if (token.isLocal)
			{
				args.mailboxId = group[0].mailboxId;
			}
			const [{result}] = await this.clients[profileID].requestMany((t) => ({
				result: t.Email.set(args) as any,
			}));
			if (result?.notDestroyed && Object.keys(result.notDestroyed).length)
			{
				throw new JmapUserError(describeSetError(result.notDestroyed) ?? this.egw.lang('Failed to delete one or more messages'));
			}
		}));
	}

	private withKeyword(filter : EmailFilter, keyword : string, set : boolean) : EmailFilter
	{
		return {operator: 'AND', conditions: [filter, set ? {hasKeyword: keyword} : {notKeyword: keyword}]};
	}

	private async queryAllIds(client : JamClient, accountId : string, filter : EmailFilter) : Promise<string[]>
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
		}
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
		}
		if (mode === 'trash')
		{
			if (!token.trashFolder)
			{
				throw new Error('MailJmap.deleteAllMatching(): no trash folder known for this profile');
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
	 *  ajax call's response used to push these via app.mail.mail_setFolderStatus/egw.refresh)
	 */
	async purgeFolder(profileID : string, which : 'trash' | 'junk') : Promise<string>
	{
		const token = await this.ensureToken(profileID);
		if (!token)
		{
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
		}
		const folder = which === 'trash' ? token.trashFolder : token.junkFolder;
		if (!folder)
		{
			throw new Error(`MailJmap.purgeFolder(): no ${which} folder known for this profile`);
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
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
			throw new Error(this.egw.lang('Unable to connect to the mail server'));
		}
		const client = this.clients[profileID];
		const mailboxId = await this.mailboxId(client, token.accountId, profileID, folder);
		const ids = await this.queryAllIds(client, token.accountId, this.buildFilter(query, mailboxId));
		const patch : Record<string, boolean | null> = {};
		['$label1', '$label2', '$label3', '$label4', '$label5',
			...Object.keys(this.app.mail_getCustomLabels()).map(id => '$' + id.toLowerCase())]
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
		Object.keys(this.app.mail_getCustomLabels()).forEach(labelId =>
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

		// mail_ui::header2gridelements()'s convention (relied on by app.ts's mail_preview(), which
		// concats "primary address" + "additional addresses" into one list for the preview panel):
		// toaddress/fromaddress hold only the *first* address as a single string, any further
		// recipients go in additionaltoaddress/additionalfromaddress as one string each. cc/bcc
		// have no such split - there's no single-value list column for them, so every recipient
		// goes in ccaddress/bccaddress as one string each.
		const fromList = addressList(email.from);
		const toList = addressList(email.to);

		const hasFlagged = keywords['$flagged'] || MailJmap.CUSTOM_FLAGS.some((flag, index) =>
			keywords['$customflag' + (index + 1)]);

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
			// MDN (read-receipt) prompt trigger - mail_preview() (app.ts) checks this against the
			// mdnsent/mdnnotsent keywords below to decide whether to show the Yes/No dialog
			dispositionnotificationto: email[MailJmap.MDN_HEADER_PROPERTY] || '',
			// Kept for the preview's attachment-presence check.  Row templates use
			// the individual image values below instead of a legacy html widget.
			attachments: email.hasAttachment ? 'attach' : '',
			attachment_icon: email.hasAttachment ? 'attach' : '',
			flagged_icon: hasFlagged ? 'unread_flagged_small' : '',
			// no attachment-list preview block for Phase 1 (see class docblock) - but app.ts's
			// mail_preview() unconditionally reads data.attachmentsBlock[0], so this must at
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
