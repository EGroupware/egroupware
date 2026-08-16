/**
 * Build one level's worth of Et2Tree node data from JMAP Mailbox objects - the client-side
 * counterpart of mail_tree.inc.php's getTree()/setOutStructure(), for the lazy per-level
 * folder-tree loading path (see doc/ai/projects/mail-folder-tree-jmap.md).
 *
 * A standalone module (not a MailApp method) so it stays trivially unit-testable - same
 * reasoning as attachmentIndex.ts.
 *
 * Field names deliberately match mail's own Tree.php override (mail/src/Tree.php), not the base
 * Etemplate Tree widget's: `id` (not `value`), `text` (not `label`), `tooltip`, `item` (not
 * `children`), `child` (not `hasChildren`) - mail_tree.inc.php already emits this shape, and
 * Et2Tree.ts's _optionTemplate() supports both naming schemes via fallbacks.
 *
 * @link https://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/** One node as returned by JmapShim::mailboxGet()/Api\Mail\Jmap's Mailbox/get (real JMAP) */
export interface JmapMailboxNode
{
	id : string;
	name : string;
	parentId : string | null;
	sortOrder? : number;
	isSubscribed : boolean;
	totalEmails? : number;
	unreadEmails? : number;
	role : string | null;
	hasChildren? : boolean;
}

export interface BuildFolderLevelOptions
{
	/** drop mailboxes with isSubscribed:false from this level */
	subscribedOnly? : boolean;
}

/**
 * mail's tree-node id ("profileID::canonical/path") is a different scheme from the raw JMAP
 * Mailbox id (base64(path) for the local shim, an opaque server-assigned string for real JMAP/
 * Stalwart) - it's what the rest of the app (mail_changeFolder(), NextMatch's selectedFolder
 * filter, MailJmap.getRows()'s own folder-path parsing) already expects. Every node keeps its
 * raw JMAP id too (under `jmapId`), purely so a *later* expand of that same node can pass it
 * straight back as getMailboxChildren()'s parentId without having to re-derive it - Stalwart's
 * opaque ids in particular can't be reconstructed from a path at all.
 */
export interface FolderTreeNode
{
	id : string;
	jmapId : string;
	text : string;
	tooltip : string;
	checked : boolean;
	child : boolean;
	item : any[];
	badge? : number;
	im0 : string;
	im1 : string;
	im2 : string;
}

// role -> classic mail_tree.inc.php $definedFolders special-folder icon name ("MailFolder"+key) -
// only the roles classic code already special-cased (Templates/Outbox/Ham have no JMAP role
// equivalent; "archive" was never special-cased classically either, so it falls through to the
// generic folder icon below rather than inventing new icon art)
const ROLE_ICON_NAMES : Record<string, string> = {
	trash: 'MailFolderTrash',
	sent: 'MailFolderSent',
	drafts: 'MailFolderDrafts',
	junk: 'MailFolderJunk',
};

/**
 * @param egw only .image(name, app) is used, kept minimal so callers/tests don't need a full
 *  egw instance (same pattern attachmentIndex.ts's renderAttachmentIndex() uses)
 */
function icons(role : string | null, egw : { image(name : string, app? : string) : string })
{
	if (role === 'inbox')
	{
		const home = egw.image('kfm_home', 'mail');
		return {im0: home, im1: home, im2: home};
	}
	const roleIcon = role && ROLE_ICON_NAMES[role] ? egw.image(ROLE_ICON_NAMES[role], 'mail') : null;
	if (roleIcon)
	{
		return {im0: roleIcon, im1: roleIcon, im2: roleIcon};
	}
	return {
		im0: egw.image('MailFolderClosed', 'mail'),
		im1: egw.image('folderOpen', 'mail'),
		im2: egw.image('MailFolderClosed', 'mail'),
	};
}

/**
 * Convert one level's worth of sibling JMAP Mailbox nodes (already fetched via
 * MailJmap.getMailboxChildren()) into mail's Et2Tree node shape.
 *
 * Unlike classic mail_tree.inc.php's ancestor-chain-preserving subscribed-folder filtering (it
 * builds the whole visible tree in one pass, so it must keep an unsubscribed ancestor visible
 * if any of its descendants are subscribed), this filters each level independently as it's
 * lazily fetched - an unsubscribed folder simply isn't shown, along with everything under it,
 * consistent with what "only show subscribed folders" means one level at a time. This is a
 * deliberate scope difference, not an oversight: with lazy per-level loading there's no cheap
 * way to know in advance whether a deeply-nested descendant several levels down is subscribed
 * without eagerly fetching the whole subtree first - exactly what this feature exists to avoid.
 *
 * `child` (Et2Tree's lazy/expandable flag) is set whenever `hasChildren` is true *or unknown*
 * (undefined) - real JMAP (Stalwart) has no "has children" hint at all, so every non-leaf-role
 * mailbox must default to "assume expandable"; Et2Tree's own handleItemLazyLoad() already
 * self-corrects (clears the flag) if a first expand comes back with zero children.
 *
 * @param mailboxes one level's worth of sibling Mailbox nodes
 * @param profileID owning mail account's profile id
 * @param parentPath the expanded parent's own canonical "/"-joined path, '' for the top level -
 *  used to build each child's tree-facing id (see FolderTreeNode's docblock)
 * @param options
 * @param egw only .image(name, app) is used
 * @return Et2Tree node data (mail's field names - see FolderTreeNode)
 */
export function buildFolderLevel(mailboxes : JmapMailboxNode[], profileID : string, parentPath : string,
	options : BuildFolderLevelOptions = {}, egw : { image(name : string, app? : string) : string }) : FolderTreeNode[]
{
	return (mailboxes || [])
		.filter((mailbox) => !options.subscribedOnly || mailbox.isSubscribed)
		.map((mailbox) : FolderTreeNode =>
		{
			const path = parentPath ? parentPath + '/' + mailbox.name : mailbox.name;
			return {
				id: profileID + '::' + path,
				jmapId: mailbox.id,
				text: mailbox.name,
				tooltip: mailbox.name,
				checked: !!mailbox.isSubscribed,
				child: mailbox.hasChildren !== false,
				item: [],
				// unread count, same information mail_tree.inc.php's classic setOutStructure()
				// showed via a "(n)" label suffix + bold style - a badge is Et2Tree's more modern
				// equivalent (_optionTemplate() already renders selectOption.badge as an sl-badge)
				...(mailbox.unreadEmails ? {badge: mailbox.unreadEmails} : {}),
				...icons(mailbox.role, egw),
			};
		});
}
