import {assert} from "@open-wc/testing";
import {buildErrorNode, buildFolderLevel, JmapMailboxNode} from "../folderTree";

/**
 * Test buildFolderLevel() - converts one level's worth of JMAP Mailbox objects (already fetched
 * via MailJmap.getMailboxChildren()) into Et2Tree's node shape for mail's lazy per-level
 * folder-tree loading (see doc/ai/projects/mail-folder-tree-jmap.md).
 *
 * Pure data transform, no DOM/network involved - only minimal egw.image()/lang() stubs are needed.
 */

const egw = {
	image: (name : string, app? : string) => `https://example.com/${app}/${name}.svg`,
	lang: (key : string) => `translated(${key})`,
};

function mailbox(overrides : Partial<JmapMailboxNode>) : JmapMailboxNode
{
	return {
		id: "jmap-opaque-id",
		name: "Folder",
		parentId: null,
		isSubscribed: true,
		role: null,
		hasChildren: undefined,
		...overrides,
	};
}

/** build() wraps buildFolderLevel() with a fixed profileID/parentPath, since most tests here
 *  only care about the per-mailbox fields, not the id-construction itself (that's covered by
 *  its own describe() block below). Defaults isTopLevel:true - most of these tests are exactly
 *  about the top-level role-based icon/label behaviour; its own describe() block below covers
 *  the isTopLevel:false restriction specifically. */
function build(mailboxes : JmapMailboxNode[], options : { subscribedOnly? : boolean, isTopLevel? : boolean } = {})
{
	return buildFolderLevel(mailboxes, "42", "", {isTopLevel: true, ...options}, egw);
}

describe("buildFolderLevel()", () =>
{
	it("maps a plain mailbox to mail's Et2Tree field names (text/tooltip/item)", () =>
	{
		const [node] = build([mailbox({name: "Inbox"})]);

		assert.equal(node.text, "Inbox");
		assert.equal(node.tooltip, "", "no role - tooltip would equal the label, so it's omitted");
		assert.deepEqual(node.item, []);
	});

	it("sets checked from isSubscribed", () =>
	{
		const [subscribed] = build([mailbox({isSubscribed: true})]);
		const [unsubscribed] = build([mailbox({isSubscribed: false})]);

		assert.isTrue(subscribed.checked);
		assert.isFalse(unsubscribed.checked);
	});

	it("filters out unsubscribed mailboxes when subscribedOnly is set", () =>
	{
		const nodes = build([
			mailbox({name: "a", isSubscribed: true}),
			mailbox({name: "b", isSubscribed: false}),
		], {subscribedOnly: true});

		assert.equal(nodes.length, 1);
		assert.equal(nodes[0].text, "a");
	});

	it("keeps unsubscribed mailboxes when subscribedOnly is not set", () =>
	{
		const nodes = build([
			mailbox({name: "a", isSubscribed: true}),
			mailbox({name: "b", isSubscribed: false}),
		], {subscribedOnly: false});

		assert.equal(nodes.length, 2);
	});

	/**
	 * The shared/other-users namespace root ("user"/"shared") is a structural navigation doorway,
	 * essentially never itself isSubscribed - subscribedOnly must never hide it, since JmapShim
	 * already only reports it here when it has real accessible children (see
	 * JmapShim::namespaceRootsMissingFrom()'s own docblock). Real regression found live: acc_id=42's
	 * "user" sibling of INBOX still disappeared after the server-side fix, because this client-side
	 * filter had the exact same gap.
	 */
	it("never filters out the namespace root even when unsubscribed", () =>
	{
		const nodes = build([
			mailbox({name: "INBOX", role: "inbox", isSubscribed: true}),
			mailbox({name: "user", isSubscribed: false}),
			mailbox({name: "Sent", role: "sent", isSubscribed: false}),
		], {subscribedOnly: true});

		assert.deepEqual(nodes.map((n) => n.text), ["translated(INBOX)", "user"]);
	});

	/**
	 * Belt-and-braces: the namespace-root subscribedOnly exemption above must not resurrect a
	 * namespace root with no children at all (hasChildren === false) - an always-visible-but-empty
	 * "user"/"shared" entry is exactly the confusing dead end this whole exemption must avoid.
	 */
	it("still filters out an unsubscribed namespace root with no children", () =>
	{
		const nodes = build([
			mailbox({name: "INBOX", role: "inbox", isSubscribed: true}),
			mailbox({name: "user", isSubscribed: false, hasChildren: false}),
		], {subscribedOnly: true});

		assert.deepEqual(nodes.map((n) => n.text), ["translated(INBOX)"]);
	});

	it("marks child=true when hasChildren is true", () =>
	{
		const [node] = build([mailbox({hasChildren: true})]);
		assert.isTrue(node.child);
	});

	it("marks child=true when hasChildren is unknown (real JMAP has no such hint)", () =>
	{
		const [node] = build([mailbox({hasChildren: undefined})]);
		assert.isTrue(node.child, "a guaranteed-leaf folder just briefly shows an expand affordance that Et2Tree's own lazy-load self-corrects on first (empty) expand");
	});

	it("marks child=false only when hasChildren is explicitly false", () =>
	{
		const [node] = build([mailbox({hasChildren: false})]);
		assert.isFalse(node.child);
	});

	it("sets a badge from unreadEmails, and omits it when zero", () =>
	{
		const [withUnread] = build([mailbox({unreadEmails: 5})]);
		const [noUnread] = build([mailbox({unreadEmails: 0})]);

		assert.equal(withUnread.badge, 5);
		assert.isUndefined(noUnread.badge);
	});

	it("uses the inbox icon for role=inbox", () =>
	{
		const [node] = build([mailbox({role: "inbox"})]);
		assert.include(node.im0, "download");
	});

	/**
	 * An open mail account's INBOX should always auto-expand alongside it if it has children,
	 * regardless of whether "profileID::INBOX" happens to also be in the persisted expand-state
	 * set - every Dovecot/IMAP account has an INBOX and (per ralf) it always has children in
	 * practice, so this shouldn't need a manual re-expand click every time.
	 */
	it("auto-opens INBOX when it has children, but not otherwise", () =>
	{
		const [withChildren] = build([mailbox({role: "inbox", hasChildren: true})]);
		const [unknownChildren] = build([mailbox({role: "inbox", hasChildren: undefined})]);
		const [noChildren] = build([mailbox({role: "inbox", hasChildren: false})]);

		assert.isTrue(withChildren.open);
		assert.isTrue(unknownChildren.open, "hasChildren unknown (real JMAP) still assumes expandable, same as `child`");
		assert.isUndefined(noChildren.open);
	});

	it("does not auto-open a non-inbox folder, even one with children", () =>
	{
		const [trash] = build([mailbox({role: "trash", hasChildren: true})]);
		const [plain] = build([mailbox({role: null, hasChildren: true})]);

		assert.isUndefined(trash.open);
		assert.isUndefined(plain.open);
	});

	it("does not auto-open INBOX at a deeper level (isTopLevel:false)", () =>
	{
		const [node] = buildFolderLevel([mailbox({role: "inbox", hasChildren: true, name: "INBOX"})],
			"42", "Archives/2020", {isTopLevel: false}, egw);

		assert.isUndefined(node.open);
	});

	/**
	 * Uses the RESOLVED bootstrap-icon name directly (the right-hand side of Api\Image::find()'s
	 * $global2bootstrap map, api/src/Image.php - eg. "dhtmlxtree/MailFolderTrash" => "trash"),
	 * not the legacy "dhtmlxtree/..." alias classic mail_tree.inc.php still goes through.
	 */
	it("uses the matching special-folder icon for trash/sent/drafts/junk/templates/outbox/archive roles", () =>
	{
		const [trash] = build([mailbox({role: "trash"})]);
		const [sent] = build([mailbox({role: "sent"})]);
		const [drafts] = build([mailbox({role: "drafts"})]);
		const [junk] = build([mailbox({role: "junk"})]);
		const [templates] = build([mailbox({role: "templates"})]);
		const [outbox] = build([mailbox({role: "outbox"})]);
		const [archive] = build([mailbox({role: "archive"})]);

		assert.include(trash.im0, "trash");
		assert.include(sent.im0, "send");
		assert.include(drafts.im0, "pencil-square");
		assert.include(junk.im0, "exclamation-octagon");
		assert.include(templates.im0, "file-earmark-text");
		assert.include(outbox.im0, "upload");
		assert.include(archive.im0, "archive");
	});

	it("uses the people icon for the shared/other-users namespace root ('user'/'shared'), regardless of case", () =>
	{
		const [user] = build([mailbox({name: "user", role: null})]);
		const [shared] = build([mailbox({name: "Shared", role: null})]);
		const [notARoot] = build([mailbox({name: "username", role: null})]);

		assert.include(user.im0, "people");
		assert.include(shared.im0, "people");
		assert.notInclude(notARoot.im0, "people", "must match the literal namespace-root name only, not a substring/prefix");
	});


	/**
	 * Classic mail_tree.inc.php always substituted the UI-language lang() translation for a
	 * role-identifiable special folder, discarding the account's own real IMAP/JMAP name - not a
	 * preference, a guarantee that eg. Trash reads the same across every account regardless of
	 * what that particular account's server happens to literally call it (some accounts' real
	 * folder names are already in the UI language, some aren't - passing the raw name through
	 * unconditionally, as buildNode() briefly did, showed inconsistent per-account translation).
	 */
	it("translates the label for role-identifiable special folders, discarding the raw name", () =>
	{
		const [inbox] = build([mailbox({role: "inbox", name: "Posteingang"})]);
		const [trash] = build([mailbox({role: "trash", name: "Papierkorb"})]);
		const [templates] = build([mailbox({role: "templates", name: "Vorlagen"})]);

		assert.equal(inbox.text, "translated(INBOX)");
		assert.equal(trash.text, "translated(Trash)");
		assert.equal(templates.text, "translated(Templates)");
	});

	/**
	 * Classic mail_tree.inc.php's own tooltip for a role-identifiable folder was the untranslated
	 * canonical key (eg. "Trash"), not the lang()-translated label ("Papierkorb") - a fixed,
	 * language-independent reference to what the folder actually is. INBOX was the one exception:
	 * its tooltip was translated too, same as its label.
	 */
	it("uses the untranslated role key as tooltip when it differs from the translated label", () =>
	{
		const [trash] = build([mailbox({role: "trash", name: "Papierkorb"})]);

		assert.equal(trash.tooltip, "Trash", "must be the raw canonical key, not translated(Trash)");
	});

	it("omits the tooltip (empty string) whenever it would be identical to the label", () =>
	{
		const [plain] = build([mailbox({role: null, name: "Projects"})]);

		assert.equal(plain.text, "Projects");
		assert.equal(plain.tooltip, "", "raw name already equals the label - no point duplicating it");
	});

	it("keeps the raw name for a folder with no role at all", () =>
	{
		const [plain] = build([mailbox({role: null, name: "Projects"})]);

		assert.equal(plain.text, "Projects");
	});

	it("translates the label for an archive-role folder too, discarding the raw name", () =>
	{
		const [archive] = build([mailbox({role: "archive", name: "Archives"})]);

		assert.equal(archive.text, "translated(Archive)");
	});

	it("falls back to the generic folder icon for a plain (non-special) folder", () =>
	{
		const [node] = build([mailbox({role: null})]);
		assert.include(node.im0, "folder2");
		assert.include(node.im1, "folder2-open");
	});

	it("returns an empty array for an empty/missing input", () =>
	{
		assert.deepEqual(build([]), []);
		assert.deepEqual(buildFolderLevel(undefined as any, "42", "", {}, egw), []);
	});

	/**
	 * Classic mail_tree.inc.php only ever special-cased folder icons/names at the top level
	 * because ITS OWN role detection (an expensive IMAP attribute lookup) was only ever computed
	 * there - not because a deeper role was meaningless. JMAP has no such limitation: a real or
	 * shim-reported role is trustworthy at any depth (eg. a shared/other-user mailbox's own
	 * INBOX/Trash, several levels deep under "Shared Folders"), so label/icon treatment now
	 * applies regardless of isTopLevel. Only the INBOX auto-open behaviour stays isTopLevel-gated
	 * (see the "does not auto-open INBOX at a deeper level" test above) - the current account's
	 * own INBOX should auto-expand, not every shared mailbox's own INBOX nested in the tree.
	 */
	describe("isTopLevel:false - label/icon treatment still applies, only auto-open stays gated", () =>
	{
		it("still translates the label and uses the role icon for trash/inbox", () =>
		{
			const [trash] = buildFolderLevel([mailbox({role: "trash", name: "Trash"})], "42", "Archives/2020", {isTopLevel: false}, egw);
			const [inbox] = buildFolderLevel([mailbox({role: "inbox", name: "INBOX"})], "42", "Archives/2020", {isTopLevel: false}, egw);

			assert.equal(trash.text, "translated(Trash)");
			assert.include(trash.im0, "trash");
			assert.include(inbox.im0, "download", "still uses the inbox/home icon");
			assert.isUndefined(inbox.open, "must not auto-open - that's still isTopLevel-gated");
		});
	});
});

describe("buildFolderLevel() id construction", () =>
{
	it("builds a top-level id as 'profileID::name', keeping the raw jmapId separately", () =>
	{
		const [node] = buildFolderLevel([mailbox({id: "opaque-1", name: "Sent"})], "42", "", {}, egw);

		assert.equal(node.id, "42::Sent");
		assert.equal(node.jmapId, "opaque-1");
	});

	it("joins onto parentPath with '/' for a nested level", () =>
	{
		const [node] = buildFolderLevel([mailbox({id: "opaque-2", name: "2026"})], "42", "INBOX/Project", {}, egw);

		assert.equal(node.id, "42::INBOX/Project/2026");
	});

	it("never derives id from the (possibly opaque, real-JMAP) jmapId - only from profileID+parentPath+name", () =>
	{
		// a real JMAP (Stalwart) mailbox id is an opaque server-assigned string, not
		// reconstructable into a path at all - the tree-facing id must never depend on it
		const [node] = buildFolderLevel([mailbox({id: "aBcD123", name: "Projects"})], "7", "INBOX", {}, egw);

		assert.equal(node.id, "7::INBOX/Projects");
		assert.equal(node.jmapId, "aBcD123");
	});
});

describe("buildErrorNode()", () =>
{
	it("puts the error message into both text and tooltip", () =>
	{
		const node = buildErrorNode("42", "INBOX/Project", "Server unreachable", egw);

		assert.equal(node.text, "Server unreachable");
		assert.equal(node.tooltip, "Server unreachable");
	});

	it("builds its id from profileID + parentPath, falling back to INBOX at the top level", () =>
	{
		const nested = buildErrorNode("42", "INBOX/Project", "boom", egw);
		const topLevel = buildErrorNode("42", "", "boom", egw);

		assert.equal(nested.id, "42::INBOX/Project");
		assert.equal(topLevel.id, "42::INBOX");
	});

	it("is never checked, never expandable, and has no children", () =>
	{
		const node = buildErrorNode("42", "", "boom", egw);

		assert.isFalse(node.checked);
		assert.isFalse(node.child);
		assert.deepEqual(node.item, []);
	});

	it("uses the no-select icon variant, same as classic's treeLeafNoConnectionArray()", () =>
	{
		const node = buildErrorNode("42", "", "boom", egw);

		assert.include(node.im0, "folderNoSelectClosed");
		assert.include(node.im1, "folderNoSelectOpen");
		assert.include(node.im2, "folderNoSelectClosed");
	});
});
