/**
 * mail - direct client-side JMAP access for Stalwart/JMAP backed accounts
 *
 * Phase 1: getRows() only - a standalone, directly-callable JMAP replica of
 * mail_ui::get_rows(), NOT yet wired into the NextMatch data-fetch pipeline,
 * and no server-side action (reply/delete/move/flag/...) understands the
 * row-ids produced here yet. See mail_ui::splitRowID()/generateJmapRowID()
 * for the server-side counterpart of the row-id scheme used below.
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
}

export interface JmapGetRowsQuery
{
	selectedFolder : string;	// "profileID::folder/path", same as mail_ui::get_rows()'s $query['selectedFolder']
	start? : number;
	num_rows? : number;
	sort? : string;				// 'ASC' | 'DESC'
	order? : string;			// column id, e.g. 'date', 'subject', 'fromaddress', ...
	cat_id? : string;			// search-type, see mail_ui::$searchTypes
	search? : string;
	filter? : string;			// status filter, see mail_ui::$statusTypes
	startdate? : string;
	enddate? : string;
}

type EmailFilterCondition = Record<string, any>;
type EmailFilter = EmailFilterCondition | {operator : 'AND' | 'OR' | 'NOT', conditions : EmailFilter[]};

/**
 * Direct JMAP access, used only for accounts backed by Imap\Stalwart (see mail_ui::ajax_jmapBootstrap)
 */
export class MailJmap
{
	protected app : MailApp;

	// keyed by profileID, since a user may have several JMAP-backed mail accounts
	private tokens : Record<string, JmapToken> = {};
	private tokenPromises : Record<string, Promise<JmapToken | null>> = {};
	private clients : Record<string, JamClient> = {};
	private refreshTimers : Record<string, number> = {};
	// "profileID::folder/path" -> JMAP Mailbox id
	private mailboxIds : Record<string, string> = {};

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
	 * JMAP replica of mail_ui::get_rows(): fetch mail-list rows directly from the JMAP server
	 *
	 * Never throws for "this account/folder just isn't JMAP-eligible" - returns null in that
	 * case, so callers can transparently fall back to the regular server-side get_rows.
	 *
	 * @param query same shape as mail_ui::get_rows()'s $query
	 * @return null if this account has no usable JMAP access-token (not Stalwart, MFA, ...)
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
			const emails = t.Email.get({
				accountId: token.accountId,
				ids: ids.$ref('/ids'),
				properties: [
					'id', 'keywords', 'size', 'receivedAt', 'sentAt', 'subject', 'preview',
					'from', 'to', 'cc', 'bcc', 'hasAttachment',
				],
			});
			return {ids, emails};
		});

		return {
			rows: (emails.list || []).map((email : any) => this.email2row(email, profileID, mailboxId)),
			total: ids.total ?? (emails.list || []).length,
		};
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
						return null;
					}
					const token : JmapToken = {
						sessionUrl: data.sessionUrl,
						accountId: data.accountId,
						access_token: data.access_token,
						expires_at: Date.now() + Math.max(0, data.expires_in - 60) * 1000,
					};
					this.tokens[profileID] = token;
					this.clients[profileID] = new JamClient({
						sessionUrl: token.sessionUrl,
						bearerToken: token.access_token,
					});
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
	 * Map mail_ui::get_rows()'s $query (cat_id/search/filter/startdate/enddate) to a JMAP
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

		switch ((query.filter || '').toLowerCase())
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
			// 'deleted': no JMAP equivalent, see method docblock
		}

		return conditions.length === 1 ? conditions[0] : {operator: 'AND', conditions};
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
	 * Map a JMAP Email object to a row shaped like mail_ui::header2gridelements()'s output
	 *
	 * Simplified for Phase 1: no smime-type detection, no attachment-list preview block,
	 * no X-Priority header. row_id uses mail_ui::generateJmapRowID()'s scheme so server-side
	 * splitRowID() can recognise and decode it once actions are migrated.
	 */
	private email2row(email : any, profileID : string, mailboxId : string) : any
	{
		const address = (list : { name? : string, email : string }[]) =>
			(list || []).map(a => a.name ? `${a.name} <${a.email}>` : a.email).join(', ');

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

		let status_icon : string;
		if (keywords['$forwarded']) status_icon = 'mail_forward';
		else if (keywords['$answered']) status_icon = 'mail_reply';
		else if (keywords['$flagged'] && !keywords['$seen']) status_icon = 'mail_flagged_unseen';
		else if (keywords['$flagged']) status_icon = 'mail_flagged_seen';
		else if (!keywords['$seen']) status_icon = 'mail_unseen';

		const fromaddress = address(email.from);
		const toaddress = address(email.to);

		return {
			row_id: this.app.egw.user('account_id') + '::' + profileID + '::' + mailboxId + '::' + email.id,
			uid: email.id,
			subject: email.subject || '(' + this.egw.lang('no subject') + ')',
			fromaddress,
			toaddress,
			ccaddress: address(email.cc),
			bccaddress: address(email.bcc),
			address: fromaddress,
			date: email.sentAt || email.receivedAt,
			modified: email.receivedAt,
			size: email.size,
			bodypreview: email.preview,
			attachments: email.hasAttachment ? "<et2-image src='attach'></et2-image>" : '&nbsp;',
			class: css.join(' '),
			icon: 'bug-fill',
			flags,
			status_icon,
			emailTag: this.egw.preference('emailTag', 'mail') || 'onlyname',
		};
	}
}
