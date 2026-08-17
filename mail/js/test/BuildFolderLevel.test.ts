import {assert} from "@open-wc/testing";
import {buildErrorNode, buildFolderLevel, buildFolderTree, JmapMailboxNode} from "../folderTree";

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
		assert.equal(node.tooltip, "Inbox");
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
	it("uses the matching special-folder icon for trash/sent/drafts/junk/templates/outbox roles", () =>
	{
		const [trash] = build([mailbox({role: "trash"})]);
		const [sent] = build([mailbox({role: "sent"})]);
		const [drafts] = build([mailbox({role: "drafts"})]);
		const [junk] = build([mailbox({role: "junk"})]);
		const [templates] = build([mailbox({role: "templates"})]);
		const [outbox] = build([mailbox({role: "outbox"})]);

		assert.include(trash.im0, "trash");
		assert.include(sent.im0, "send");
		assert.include(drafts.im0, "pencil-square");
		assert.include(junk.im0, "exclamation-octagon");
		assert.include(templates.im0, "file-earmark-text");
		assert.include(outbox.im0, "upload");
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
		assert.equal(trash.tooltip, "translated(Trash)");
		assert.equal(templates.text, "translated(Templates)");
	});

	it("keeps the raw name for a role with no translation mapping (eg. archive) or no role at all", () =>
	{
		const [archive] = build([mailbox({role: "archive", name: "Archives"})]);
		const [plain] = build([mailbox({role: null, name: "Projects"})]);

		assert.equal(archive.text, "Archives");
		assert.equal(plain.text, "Projects");
	});

	it("falls back to the generic folder icon for a role with no dedicated icon (eg. archive)", () =>
	{
		const [node] = build([mailbox({role: "archive"})]);
		assert.include(node.im0, "folder2");
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
	 * Classic mail_tree.inc.php only ever special-cases folder icons/names at the top level
	 * (account root or INBOX's own direct children, Api\Mail::getFolderArrays()'s
	 * $_onlyTopLevel mode) - a deeper level always uses plain generic icons and the raw name,
	 * even for a mailbox that happens to carry a matching role (eg. a real \Trash-attributed
	 * folder several levels deep would still get no special treatment classically).
	 */
	describe("isTopLevel:false - deeper levels never get role-based icon/label treatment", () =>
	{
		it("ignores a reported role entirely, even trash/inbox", () =>
		{
			const [trash] = buildFolderLevel([mailbox({role: "trash", name: "Trash"})], "42", "Archives/2020", {isTopLevel: false}, egw);
			const [inbox] = buildFolderLevel([mailbox({role: "inbox", name: "INBOX"})], "42", "Archives/2020", {isTopLevel: false}, egw);

			assert.equal(trash.text, "Trash", "must keep the raw name, not translated(Trash)");
			assert.include(trash.im0, "folder2", "must use the generic icon, not the trash icon");
			assert.include(inbox.im0, "folder2", "must use the generic icon, not the home icon");
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

describe("buildFolderTree()", () =>
{
	/**
	 * Same "top level" restriction as buildFolderLevel()'s isTopLevel option, computed per-node
	 * here since this builds the whole nested tree in one pass: INBOX's own direct child gets
	 * role-based treatment, but a grandchild (two levels deep) does not, even with a matching role.
	 */
	it("only applies role-based icon/label treatment at the account root or INBOX's own children", () =>
	{
		const tree = buildFolderTree([
			mailbox({id: "inbox", name: "INBOX", role: "inbox", parentId: null}),
			mailbox({id: "trash", name: "Trash", role: "trash", parentId: "inbox"}),
			mailbox({id: "old", name: "Trash", role: "trash", parentId: "trash"}),
		], "42", egw);

		const trashNode = tree[0].item[0];
		const deepNode = trashNode.item[0];

		assert.equal(tree[0].text, "translated(INBOX)");
		assert.equal(trashNode.text, "translated(Trash)");
		assert.equal(deepNode.text, "Trash", "two levels deep must keep the raw name");
		assert.include(deepNode.im0, "folder2", "two levels deep must use the generic icon");
	});

	it("nests children under their parent via parentId, all the way down", () =>
	{
		const tree = buildFolderTree([
			mailbox({id: "inbox", name: "INBOX", parentId: null}),
			mailbox({id: "projects", name: "Projects", parentId: "inbox"}),
			mailbox({id: "2026", name: "2026", parentId: "projects"}),
		], "42", egw);

		assert.equal(tree.length, 1);
		assert.equal(tree[0].text, "INBOX");
		assert.equal(tree[0].item.length, 1);
		assert.equal(tree[0].item[0].text, "Projects");
		assert.equal(tree[0].item[0].item[0].text, "2026");
		assert.equal(tree[0].item[0].item[0].item.length, 0);
	});

	it("builds ids from the real nested path, not the flat list's order", () =>
	{
		const tree = buildFolderTree([
			mailbox({id: "inbox", name: "INBOX", parentId: null}),
			mailbox({id: "projects", name: "Projects", parentId: "inbox"}),
		], "42", egw);

		assert.equal(tree[0].id, "42::INBOX");
		assert.equal(tree[0].item[0].id, "42::INBOX/Projects");
		assert.equal(tree[0].item[0].jmapId, "projects");
	});

	it("sets child from the real children, not the hasChildren hint", () =>
	{
		const tree = buildFolderTree([
			mailbox({id: "a", name: "a", parentId: null, hasChildren: true}),
			mailbox({id: "b", name: "b", parentId: null, hasChildren: undefined}),
			mailbox({id: "c", name: "c", parentId: "a"}),
		], "42", egw);

		const [a, b] = tree;
		assert.isTrue(a.child, "a has a real child in the flat list");
		assert.isFalse(b.child, "b's hasChildren hint is ignored - it has no real children here");
	});

	it("keeps multiple root-level mailboxes as siblings", () =>
	{
		const tree = buildFolderTree([
			mailbox({id: "a", name: "INBOX", parentId: null}),
			mailbox({id: "b", name: "Sent", parentId: null}),
		], "42", egw);

		assert.equal(tree.length, 2);
		assert.sameMembers(tree.map((n) => n.text), ["INBOX", "Sent"]);
	});

	it("returns an empty array for an empty/missing input", () =>
	{
		assert.deepEqual(buildFolderTree([], "42", egw), []);
		assert.deepEqual(buildFolderTree(undefined as any, "42", egw), []);
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
