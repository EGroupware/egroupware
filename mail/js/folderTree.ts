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
	/**
	 * classic mail_tree.inc.php only ever special-cases folder icons/names (Trash, Sent,
	 * Templates, ...) at the account root or INBOX's own direct children - never at any deeper
	 * level, even for a folder that happens to carry a matching name/role. Defaults to false
	 * (safe default for callers that don't pass it, eg. tests) - MailApp.mail_buildFolderLevelData()
	 * always computes and passes this explicitly.
	 */
	isTopLevel? : boolean;
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
	/** only ever set true - see buildNode()'s INBOX auto-open comment; omitted (not false)
	 *  otherwise, so it never fights Et2Tree.ts's own openStatePreference-driven state */
	open? : true;
}

// role -> bootstrap-icon name, i.e. the RIGHT-hand (already-translated) side of Api\Image::find()'s
// $global2bootstrap map (api/src/Image.php) - eg. 'dhtmlxtree/MailFolderTrash' => 'trash' there,
// used here as just 'trash' directly, skipping the legacy "dhtmlxtree/" alias indirection that
// classic mail_tree.inc.php still goes through for historical reasons. "templates"/"outbox" are
// EGroupware-specific extensions (see roleFor()'s docblock, mail/src/JmapShim.php): neither has
// an IMAP SPECIAL-USE attribute or a JMAP role at all - JmapShim resolves them server-side via the
// account's own acc_folder_template/acc_folder_outbox config, and for a real JMAP server
// (Stalwart, no equivalent mechanism) MailJmap.getMailboxChildren() (mail/js/jmap.ts) matches by
// the account's own configured folder name (from the JMAP bootstrap payload) before this shape
// even reaches buildNode() - so `mailbox.role` is already correctly set by the time it gets here
// for both backends, no name-guessing needed in this shared code.
const ROLE_ICON_NAMES : Record<string, string> = {
	trash: 'trash',
	sent: 'send',
	drafts: 'pencil-square',
	junk: 'exclamation-octagon',
	templates: 'file-earmark-text',
	outbox: 'upload',
	archive: 'archive',
};

/**
 * @param role
 * @param isNamespaceRoot the shared/other-users namespace root ("user"/"shared" - see
 *  buildFolderLevel()'s own isVisibleNamespaceRoot()) gets its own icon regardless of role (it
 *  never has one anyway - it's a structural navigation doorway, not a real mailbox)
 * @param egw only .image(name, app) is used, kept minimal so callers/tests don't need a full
 *  egw instance (same pattern attachmentIndex.ts's renderAttachmentIndex() uses)
 */
function icons(role : string | null, isNamespaceRoot : boolean, egw : { image(name : string, app? : string) : string })
{
	if (role === 'inbox')
	{
		const home = egw.image('download', 'mail');
		return {im0: home, im1: home, im2: home};
	}
	if (isNamespaceRoot)
	{
		const people = egw.image('people', 'mail');
		return {im0: people, im1: people, im2: people};
	}
	const roleIcon = role && ROLE_ICON_NAMES[role] ? egw.image(ROLE_ICON_NAMES[role], 'mail') : null;
	if (roleIcon)
	{
		return {im0: roleIcon, im1: roleIcon, im2: roleIcon};
	}
	return {
		im0: egw.image('folder2', 'mail'),
		im1: egw.image('folder2-open', 'mail'),
		im2: egw.image('folder2', 'mail'),
	};
}

type Egw = { image(name : string, app? : string) : string, lang(key : string) : string };

// role -> lang() key for special-folder label translation, matching classic mail_tree.inc.php's
// own lang($key)/lang($folderName) calls (setOutStructure()) - a JMAP-native server may return an
// already-localized/custom Mailbox.name (this project's own Stalwart test account happens to
// store German names directly), but JmapShim (the local plain-IMAP shim) always reports the
// server's own literal English IMAP name, which was never translated without this.
const ROLE_LABEL_KEYS : Record<string, string> = {
	inbox: 'INBOX', trash: 'Trash', sent: 'Sent', drafts: 'Drafts', junk: 'Junk',
	templates: 'Templates', outbox: 'Outbox', archive: 'Archive',
};

// Fixed top-level display order (ralf's explicit spec, confirmed independent of the folder's
// actual name/translation): INBOX, then these special-role folders in this exact sequence, then
// every other folder alphabetically, then the shared/other-users namespace root last.
const TOP_LEVEL_SORT_ORDER : Record<string, number> = {
	inbox: 0, drafts: 1, templates: 2, sent: 3, trash: 4, junk: 5, outbox: 6,
};

// the shared/other-users namespace root ("user" on Dovecot/JmapShim's local shim, "shared" on a
// real JMAP server like Stalwart) is a structural navigation doorway, not a real mailbox - it
// never has a role, is matched purely by its literal (untranslated) name, same convention used
// throughout this file (sortTopLevel(), buildFolderLevel()'s own visibility exemption, buildNode()'s
// icon choice)
function isNamespaceRootName(name : string) : boolean
{
	return ['user', 'shared'].includes((name || '').toLowerCase());
}

/**
 * Sort a level's sibling JMAP Mailbox nodes into TOP_LEVEL_SORT_ORDER's fixed sequence:
 * INBOX/Drafts/Templates/Sent/Trash/Junk/Outbox in that exact order, then every other folder
 * alphabetically by name, then the shared/other-users namespace root ("user" on Dovecot/JmapShim's
 * local shim, "shared" on a real JMAP server like Stalwart) always last - ralf's explicit spec.
 * Not scoped to any particular depth: used for the account's own top level/INBOX's own children
 * (MailJmap.getMailboxChildren()) and equally for any shared/other-user mailbox's own special-
 * folder set nested deeper in the tree, wherever the server actually tagged a sibling with a role
 * (see buildNode()'s own docblock on why a role at any depth is trustworthy), and for the
 * subscribe popup's whole-tree eager build (buildFolderTree()). Mutates `list` in place.
 */
export function sortTopLevel(list : JmapMailboxNode[]) : void
{
	const priority = (m : JmapMailboxNode) : number =>
	{
		if (m.role && m.role in TOP_LEVEL_SORT_ORDER) return TOP_LEVEL_SORT_ORDER[m.role];
		if (isNamespaceRootName(m.name)) return 8;
		return 7;
	};
	list.sort((a, b) =>
	{
		const pa = priority(a), pb = priority(b);
		return pa !== pb ? pa - pb : (pa === 7 ? (a.name || '').localeCompare(b.name || '') : 0);
	});
}

/**
 * Build one FolderTreeNode - shared by buildFolderLevel() (lazy, one level at a time) and
 * buildFolderTree() (eager, the whole account at once). `item`/`child` are left at their
 * lazy-loading defaults (empty/"assume expandable", see buildFolderLevel()'s docblock);
 * buildFolderTree() overrides both once it knows the real children.
 *
 * @param mailbox
 * @param profileID owning mail account's profile id
 * @param path this node's own canonical "/"-joined path (already including its own name)
 * @param egw only .image(name, app) is used, kept minimal so callers/tests don't need a full
 *  egw instance (same pattern attachmentIndex.ts's renderAttachmentIndex() uses)
 * @param isTopLevel only gates the auto-open behaviour below (the current account's OWN INBOX
 *  should auto-expand, not every shared/other-user mailbox's own INBOX nested somewhere in the
 *  tree) - label/icon treatment uses mailbox.role unconditionally regardless of depth (see below).
 */
function buildNode(mailbox : JmapMailboxNode, profileID : string, path : string, egw : Egw, isTopLevel : boolean) : FolderTreeNode
{
	// A mailbox nested inside a shared/other-user's own namespace (eg. "Shared Folders/name@
	// example.com/INBOX") still has its own genuine special-folder set - the server (real JMAP or
	// JmapShim's roleFor()) already scopes `.role` correctly to the actual special mailbox
	// regardless of depth, it's a per-mailbox protocol semantic, not something scoped to "top of
	// MY OWN account" - classic mail_tree.inc.php only ever special-cased icons/names at the
	// account root or INBOX's own direct children because ITS OWN role detection (an expensive
	// IMAP attribute lookup) was only ever computed there in the first place, not because a
	// deeper role was meaningless; JMAP has no such limitation, role is already known for every
	// mailbox in the same response.
	const role = mailbox.role;
	const roleLabelKey = role ? ROLE_LABEL_KEYS[role] : undefined;
	// classic mail_tree.inc.php always substituted the UI-language translation for a
	// role-identifiable special folder, discarding whatever the account's own real IMAP/JMAP
	// name was - not just a preference, a guarantee that eg. Trash reads the same across every
	// account regardless of what that account's server happens to literally call it
	const label = roleLabelKey ? egw.lang(roleLabelKey) : mailbox.name;
	// classic mail_tree.inc.php's own tooltip for a role-identifiable folder was the untranslated
	// canonical key (eg. "Trash", not "Papierkorb") - a fixed, language-independent reference to
	// what this folder actually is, regardless of the UI's current language. Only shown when it
	// actually differs from the (possibly translated) label - an identical tooltip is a pointless
	// duplicate, and Et2Tree's own _optionTemplate() already treats an empty string as "no
	// tooltip" (`title=${selectOption.tooltip || selectOption.title || nothing}`).
	const tooltipCandidate = roleLabelKey ?? mailbox.name;
	const tooltip = tooltipCandidate !== label ? tooltipCandidate : '';
	const isNamespaceRoot = isNamespaceRootName(mailbox.name);
	return {
		id: profileID + '::' + path,
		jmapId: mailbox.id,
		text: label,
		tooltip,
		checked: !!mailbox.isSubscribed,
		child: mailbox.hasChildren !== false,
		item: [],
		// unread count, same information mail_tree.inc.php's classic setOutStructure()
		// showed via a "(n)" label suffix + bold style - a badge is Et2Tree's more modern
		// equivalent (_optionTemplate() already renders selectOption.badge as an sl-badge)
		...(mailbox.unreadEmails ? {badge: mailbox.unreadEmails} : {}),
		// an open mail account's INBOX should always auto-expand alongside it if it has children
		// (ralf's explicit ask) - regardless of whether "profileID::INBOX" happens to also be in
		// the persisted expand-state set (Et2Tree.ts's openStatePreference), since every Dovecot/
		// IMAP account has an INBOX and (per ralf) it always has children in practice. Only ever
		// `true`, never `false` - omitting it otherwise means this can't fight a `true` a later
		// openStatePreference restore pass sets for a NON-inbox node. Gated on isTopLevel (unlike
		// the label/icon above) - only the CURRENT account's own INBOX auto-expands, not every
		// shared/other-user mailbox's own INBOX.
		...(role === 'inbox' && isTopLevel && mailbox.hasChildren !== false ? {open: true} : {}),
		...icons(role, isNamespaceRoot, egw),
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
	options : BuildFolderLevelOptions = {}, egw : Egw) : FolderTreeNode[]
{
	// the shared/other-users namespace root ("user" on Dovecot/JmapShim's local shim, "shared" on
	// a real JMAP server like Stalwart) is a structural navigation doorway, not an individually-
	// subscribable mailbox in the normal sense - it's essentially never itself isSubscribed, so
	// the subscribedOnly filter below would otherwise hide the only way into that whole
	// namespace. Still gated on hasChildren !== false as a client-side belt-and-braces check
	// (JmapShim::namespaceRootsMissingFrom() already only reports it server-side when it has real
	// accessible children, but an empty, dead-end namespace root is exactly what this whole
	// exemption must never show for any other backend path that doesn't apply the same check)
	const isVisibleNamespaceRoot = (mailbox : JmapMailboxNode) =>
		isNamespaceRootName(mailbox.name) && mailbox.hasChildren !== false;
	return (mailboxes || [])
		.filter((mailbox) => !options.subscribedOnly || mailbox.isSubscribed || isVisibleNamespaceRoot(mailbox))
		.map((mailbox) => buildNode(mailbox, profileID, parentPath ? parentPath + '/' + mailbox.name : mailbox.name, egw,
			!!options.isTopLevel));
}

/**
 * Convert an *entire* account's flat JMAP Mailbox list (already fetched via
 * MailJmap.getMailboxTree()) into a fully-nested Et2Tree structure, for the mail.subscribe
 * popup's multi-select tree - unlike buildFolderLevel(), every node's real children are already
 * known, so `item` holds the actual nested subtree (not left empty for lazy loading) and `child`
 * reflects whether it *actually* has children, not the "assume expandable" default
 * buildFolderLevel() needs. Subscribed-state filtering doesn't apply here - this popup always
 * shows every folder, subscribed or not, since that's the whole point of a subscription manager.
 *
 * @param mailboxes every mailbox in the account, flat
 * @param profileID owning mail account's profile id
 * @param egw only .image(name, app) is used
 * @return Et2Tree node data (mail's field names - see FolderTreeNode), nested from the top level down
 */
export function buildFolderTree(mailboxes : JmapMailboxNode[], profileID : string, egw : Egw) : FolderTreeNode[]
{
	const byParent = new Map<string | null, JmapMailboxNode[]>();
	(mailboxes || []).forEach((mailbox) =>
	{
		const key = mailbox.parentId ?? null;
		if (!byParent.has(key)) byParent.set(key, []);
		byParent.get(key).push(mailbox);
	});

	// only gates buildNode()'s auto-open behaviour (see its own docblock) - computed per-node here
	// since this builds the whole nested tree in one pass
	const isTopLevel = (parentPath : string) => parentPath === '' || parentPath === 'INBOX';

	const build = (parentId : string | null, parentPath : string) : FolderTreeNode[] =>
	{
		const siblings = byParent.get(parentId) || [];
		// same fixed role-based ordering as the lazy per-level tree (MailJmap.getMailboxChildren())
		// - applies whenever this level actually contains a role-tagged mailbox (the account's own
		// top level, INBOX's own children, or any shared/other-user mailbox's own special-folder
		// set), leaving a level of ordinary personal subfolders in the server's own default order
		if (siblings.some((mailbox) => !!mailbox.role)) sortTopLevel(siblings);
		return siblings.map((mailbox) =>
		{
			const path = parentPath ? parentPath + '/' + mailbox.name : mailbox.name;
			const node = buildNode(mailbox, profileID, path, egw, isTopLevel(parentPath));
			node.item = build(mailbox.id, path);
			node.child = node.item.length > 0;
			return node;
		});
	};

	return build(null, '');
}

/**
 * Build a single error leaf for a folder-tree level that failed to load via JMAP with a real,
 * user-facing error (see MailJmap's JmapUserError) - the client-side counterpart of
 * mail_tree.inc.php's treeLeafNoConnectionArray(), used the same way: puts the error text directly
 * into the visible label/tooltip, using the same "no-select" icon variant classic already uses
 * (mail_tree.inc.php:112-114) rather than an ordinary folder icon, so it reads as an error, not a
 * normal (empty) folder.
 *
 * @param profileID owning mail account's profile id
 * @param parentPath the level that failed to load, '' for the top level
 * @param message human-readable error text (MailJmap's JmapUserError#message)
 * @param egw only .image(name, app) is used
 */
export function buildErrorNode(profileID : string, parentPath : string, message : string, egw : Egw) : FolderTreeNode
{
	return {
		id: profileID + '::' + (parentPath || 'INBOX'),
		jmapId: '',
		text: message,
		tooltip: message,
		checked: false,
		child: false,
		item: [],
		im0: egw.image('folderNoSelectClosed', 'mail'),
		im1: egw.image('folderNoSelectOpen', 'mail'),
		im2: egw.image('folderNoSelectClosed', 'mail'),
	};
}
