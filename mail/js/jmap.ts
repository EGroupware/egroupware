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

interface JmapToken
{
	sessionUrl : string;
	accountId : string;
	access_token : string;
	expires_at : number;	// ms epoch, with our own safety-margin already subtracted
	isLocal : boolean;
	customLabels : Record<string, {name : string, color : string, icon? : string}>;
}

export interface JmapMessageReference
{
	profileID : string;
	mailboxId : string;
	emailId : string;
}

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
	private static readonly QUERY_PAGE_SIZE = 500;

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
				'from', 'to', 'cc', 'bcc', 'hasAttachment',
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
				[_queriedRange.refresh] : _queriedRange.refresh);
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
	 */
	private async refreshRows(rowIds : string[]) : Promise<false | any>
	{
		try
		{
			const groups = this.groupReferences(rowIds.map(rowId => this.messageReference(rowId)));

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
				const args : any = {
					accountId: token.accountId,
					ids: refs.map(ref => ref.emailId),
					properties: [
						'id', 'keywords', 'size', 'receivedAt', 'sentAt', 'subject',
						'from', 'to', 'cc', 'bcc', 'hasAttachment', 'preview',
					],
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
			console.error('MailJmap.refreshRows(): failed, resolving as an empty result', e);
			return false;
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
						customLabels: data.customLabels || {},
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

	/** Submit a JMAP Email/set update, adding the local mailbox extension only for our shim. */
	private async updateKeywords(profileID : string, mailboxId : string,
		update : Record<string, Record<string, boolean | null>>) : Promise<any>
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
			const error : any = new Error(this.egw.lang('Failed to update one or more messages'));
			error.notUpdated = result.notUpdated;
			throw error;
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

	/** Toggle a mutually-exclusive custom flag for explicitly partitioned message ids. */
	async setCustomFlag(references : JmapMessageReference[], flagId : string, set : boolean) : Promise<void>
	{
		const keyword = this.customFlagKeyword(flagId);
		const patch = this.keywordPatch(keyword, set);
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
		patch : Record<string, boolean | null>) : Promise<void>
	{
		for (let start = 0; start < ids.length; start += MailJmap.QUERY_PAGE_SIZE)
		{
			const update : Record<string, Record<string, boolean | null>> = {};
			ids.slice(start, start + MailJmap.QUERY_PAGE_SIZE).forEach(id => update[id] = {...patch});
			await this.updateKeywords(profileID, mailboxId, update);
		}
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
			MailJmap.CUSTOM_FLAGS.forEach(other =>
			{
				const otherKeyword = this.customFlagKeyword(other);
				if (otherKeyword !== keyword)
				{
					Object.assign(setPatch, this.keywordPatch(otherKeyword, false));
				}
			});
		}
		await this.updateIds(profileID, mailboxId, setIds, setPatch);
		await this.updateIds(profileID, mailboxId, removeIds, this.keywordPatch(keyword, false));
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
	private email2row(email : any, profileID : string, mailboxId : string) : any
	{
		const addressList = (list : { name? : string, email : string }[]) =>
			(list || []).map(a => a.name ? `${a.name} <${a.email}>` : a.email);

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

		let status_icon : string;
		if (keywords['$forwarded']) status_icon = 'mail_forward';
		else if (keywords['$answered']) status_icon = 'mail_reply';
		else if (keywords['$flagged'] && !keywords['$seen']) status_icon = 'mail_flagged_unseen';
		else if (keywords['$flagged']) status_icon = 'mail_flagged_seen';
		else if (!keywords['$seen']) status_icon = 'mail_unseen';

		// mail_ui::header2gridelements()'s convention (relied on by app.ts's mail_preview(), which
		// concats "primary address" + "additional addresses" into one list for the preview panel):
		// toaddress/fromaddress hold only the *first* address as a single string, any further
		// recipients go in additionaltoaddress/additionalfromaddress as one string each. cc/bcc
		// have no such split - there's no single-value list column for them, so every recipient
		// goes in ccaddress/bccaddress as one string each.
		const fromList = addressList(email.from);
		const toList = addressList(email.to);

		let attachments = email.hasAttachment ? "<et2-image src='attach'></et2-image>" : '&nbsp;';
		if (keywords['$flagged'] || MailJmap.CUSTOM_FLAGS.some((flag, index) =>
			keywords['$customflag' + (index + 1)]))
		{
			attachments += "<et2-image src='unread_flagged_small' id='flaggedImage'></et2-image>";
		}

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
			date: email.sentAt || email.receivedAt,
			modified: email.receivedAt,
			size: email.size,
			bodypreview: email.preview || '',
			attachments,
			// no attachment-list preview block for Phase 1 (see class docblock) - but app.ts's
			// mail_preview() unconditionally reads data.attachmentsBlock[0], so this must at
			// least exist as an array or clicking a row throws and the preview never loads
			attachmentsBlock: [],
			class: css.join(' '),
			icon: 'bug-fill',
			flags,
			status_icon,
			emailTag: this.egw.preference('emailTag', 'mail') || 'onlyname',
		};
	}
}
