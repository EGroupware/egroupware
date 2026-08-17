import {assert} from "@open-wc/testing";
import {buildFolderLevel, buildFolderTree, JmapMailboxNode} from "../folderTree";

/**
 * Test buildFolderLevel() - converts one level's worth of JMAP Mailbox objects (already fetched
 * via MailJmap.getMailboxChildren()) into Et2Tree's node shape for mail's lazy per-level
 * folder-tree loading (see doc/ai/projects/mail-folder-tree-jmap.md).
 *
 * Pure data transform, no DOM/network involved - only a minimal egw.image() stub is needed.
 */

const egw = {
	image: (name : string, app? : string) => `https://example.com/${app}/${name}.svg`,
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
 *  its own describe() block below) */
function build(mailboxes : JmapMailboxNode[], options : { subscribedOnly? : boolean } = {})
{
	return buildFolderLevel(mailboxes, "42", "", options, egw);
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
		assert.include(node.im0, "kfm_home");
	});

	it("uses the matching special-folder icon for trash/sent/drafts/junk roles", () =>
	{
		const [trash] = build([mailbox({role: "trash"})]);
		const [sent] = build([mailbox({role: "sent"})]);
		const [drafts] = build([mailbox({role: "drafts"})]);
		const [junk] = build([mailbox({role: "junk"})]);

		assert.include(trash.im0, "MailFolderTrash");
		assert.include(sent.im0, "MailFolderSent");
		assert.include(drafts.im0, "MailFolderDrafts");
		assert.include(junk.im0, "MailFolderJunk");
	});

	it("falls back to the generic folder icon for a role with no dedicated icon (eg. archive)", () =>
	{
		const [node] = build([mailbox({role: "archive"})]);
		assert.include(node.im0, "MailFolderClosed");
	});

	it("falls back to the generic folder icon for a plain (non-special) folder", () =>
	{
		const [node] = build([mailbox({role: null})]);
		assert.include(node.im0, "MailFolderClosed");
		assert.include(node.im1, "folderOpen");
	});

	it("returns an empty array for an empty/missing input", () =>
	{
		assert.deepEqual(build([]), []);
		assert.deepEqual(buildFolderLevel(undefined as any, "42", "", {}, egw), []);
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
