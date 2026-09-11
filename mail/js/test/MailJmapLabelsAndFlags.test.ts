import {assert} from "@open-wc/testing";
import {JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's label/custom-flag surface (setLabel/setMdnFlag/clearLabels/
 * setCustomFlag/clearLabelsForAll/toggleForAll) - doc/ai/projects/mail-test-coverage.md's
 * priority-2 entry ("JMAP (and shim) path... take precedence over the classic Api\Mail class").
 * These share the exact groupReferences()/updateIds()/keywordPatch() machinery already exercised
 * (for move/copy) by MailJmapBulkMoveCopyDelete.test.ts.
 */

const egw = {
	user : (_key : string) => 1,
	lang : (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
	preference : (_key : string, _app? : string) => null,
	config : (_name : string, _app? : string) => null,
	request : async() => ({}),
	message : (_msg : string, _type? : string) => {},
	// a "no token" test can fall through to ensureToken()'s own popupCheckCert() call - stubbed so
	// that path is exercised without depending on its own (unrelated) implementation, same as
	// MailJmapMailboxCrud.test.ts's identical egw stub addition.
	link : (_path : string, _params : Record<string, any>) => "https://example.com/",
	open_link : () => {},
};

function createFakeApp(customLabels : Record<string, any> = {}) : MailApp
{
	return {egw, getCustomLabels : () => customLabels} as unknown as MailApp;
}

function ref(profileID : string, mailboxId : string, emailId : string)
{
	return {profileID, mailboxId, emailId};
}

function primeToken(jmap : MailJmap, profileID : string, client : any,
	overrides : Record<string, any> = {}) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		...overrides,
	};
	(jmap as any).clients[profileID] = client;
}

function seedMailboxId(jmap : MailJmap, profileID : string, folderPath : string, mailboxId : string) : void
{
	(jmap as any).mailboxIds[profileID + '::' + folderPath] = mailboxId;
}

/** Fake client for setLabel()/setMdnFlag()/setCustomFlag()/clearLabels() - a single Email/set call. */
function createSetFakeClient() : {client : any, captured : any[]}
{
	const captured : any[] = [];
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {Email : {set : (args : any) => { captured.push(args); return {updated : {}, notUpdated : {}}; }}};
			const request = buildFn(t);
			return [request];
		},
	};
	return {client, captured};
}

describe("MailJmap.setLabel()", () =>
{
	it("adds a built-in label (label1-5) as a $labelN keyword", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setLabel([ref("1", "mbx-inbox", "e1")], "label3", true);

		assert.deepEqual(captured[0].update, {e1 : {"keywords/$label3" : true}});
	});

	it("removes a built-in label", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setLabel([ref("1", "mbx-inbox", "e1")], "label3", false);

		assert.deepEqual(captured[0].update, {e1 : {"keywords/$label3" : null}});
	});

	it("resolves a custom label case-insensitively to its own lowercase keyword", async() =>
	{
		const jmap = new MailJmap(createFakeApp({MyLabel : {name : "My Label"}}));
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setLabel([ref("1", "mbx-inbox", "e1")], "MYLABEL", true);

		assert.deepEqual(captured[0].update, {e1 : {"keywords/$mylabel" : true}});
	});

	it("throws for an unknown label id", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.setLabel([ref("1", "mbx-inbox", "e1")], "notALabel", true);
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});
});

describe("MailJmap.setMdnFlag()", () =>
{
	it("sets $mdnsent when a read receipt was sent", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setMdnFlag([ref("1", "mbx-inbox", "e1")], true);

		assert.deepEqual(captured[0].update, {e1 : {"keywords/$mdnsent" : true}});
	});

	it("sets $mdnnotsent when the user declined to send a read receipt", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setMdnFlag([ref("1", "mbx-inbox", "e1")], false);

		assert.deepEqual(captured[0].update, {e1 : {"keywords/$mdnnotsent" : true}},
			"setMdnFlag(false) sets $mdnnotsent=true, it never CLEARS $mdnsent - these are two independent fixed keywords");
	});
});

describe("MailJmap.setCustomFlag()", () =>
{
	it("setting one custom flag also sets $flagged and clears every OTHER custom flag (mutual exclusivity)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setCustomFlag([ref("1", "mbx-inbox", "e1")], "customFlag2", true);

		const patch = captured[0].update.e1;
		assert.equal(patch["keywords/$customflag2"], true);
		assert.equal(patch["keywords/$flagged"], true);
		assert.equal(patch["keywords/$customflag1"], null);
		assert.equal(patch["keywords/$customflag3"], null);
		assert.equal(patch["keywords/$customflag4"], null);
		assert.equal(patch["keywords/$customflag5"], null);
	});

	it("unsetting a custom flag clears it and $flagged, without touching the other (already-clear) custom flags", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.setCustomFlag([ref("1", "mbx-inbox", "e1")], "customFlag2", false);

		const patch = captured[0].update.e1;
		assert.equal(patch["keywords/$customflag2"], null);
		assert.equal(patch["keywords/$flagged"], null);
		assert.isUndefined(patch["keywords/$customflag1"], "unsetting must not touch other custom flags' keywords at all");
	});
});

describe("MailJmap.clearLabels()", () =>
{
	it("clears all 5 built-in labels plus every configured custom label, without touching $flagged/other keywords", async() =>
	{
		const jmap = new MailJmap(createFakeApp({MyLabel : {name : "My Label"}, Urgent : {name : "Urgent"}}));
		const {client, captured} = createSetFakeClient();
		primeToken(jmap, "1", client);

		await jmap.clearLabels([ref("1", "mbx-inbox", "e1")]);

		const patch = captured[0].update.e1;
		assert.deepEqual(patch, {
			"keywords/$label1" : null, "keywords/$label2" : null, "keywords/$label3" : null,
			"keywords/$label4" : null, "keywords/$label5" : null,
			"keywords/$mylabel" : null, "keywords/$urgent" : null,
		});
	});
});

/** Fake client for toggleForAll()/clearLabelsForAll() - Email/query (twice, concurrently, for toggleForAll) + Email/set. */
function createQueryThenSetFakeClient(queryResults : (args : any) => {ids : string[], total : number}) :
	{client : any, capturedQueries : any[], capturedSets : any[]}
{
	const capturedQueries : any[] = [];
	const capturedSets : any[] = [];
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {
				Email : {
					query : (args : any) => { capturedQueries.push(args); return queryResults(args); },
					set : (args : any) => { capturedSets.push(args); return {updated : {}, notUpdated : {}}; },
				},
			};
			const request = buildFn(t);
			// requestMany() only ever carries ONE of {page, result} per call in the real code - the
			// object literal's own single key tells us which, same "request already IS the answer"
			// shape every other fake client in this test suite uses.
			return [request];
		},
	};
	return {client, capturedQueries, capturedSets};
}

describe("MailJmap.toggleForAll() - per-message toggle of a label/custom flag across a filtered query", () =>
{
	it("adds the label to messages that don't have it yet, and removes it from messages that do", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, capturedQueries, capturedSets} = createQueryThenSetFakeClient((args) =>
		{
			const condition = args.filter.conditions[1];
			return 'notKeyword' in condition ? {ids : ["without-it"], total : 1} : {ids : ["with-it"], total : 1};
		});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");

		await jmap.toggleForAll({selectedFolder : "1::INBOX"}, "label2");

		assert.equal(capturedQueries.length, 2, "must query both directions (has/doesn't have) concurrently");
		assert.sameMembers(capturedQueries.map(q => q.filter.conditions[1].hasKeyword ?? q.filter.conditions[1].notKeyword),
			["$label2", "$label2"]);

		assert.equal(capturedSets.length, 2);
		const setCall = capturedSets.find(c => Object.keys(c.update).includes("without-it"));
		const removeCall = capturedSets.find(c => Object.keys(c.update).includes("with-it"));
		assert.deepEqual(setCall.update, {"without-it" : {"keywords/$label2" : true}});
		assert.deepEqual(removeCall.update, {"with-it" : {"keywords/$label2" : null}});
	});

	it("for a custom flag, additionally sets/clears $flagged and clears the OTHER custom flags only on the 'set' side", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, capturedSets} = createQueryThenSetFakeClient((args) =>
		{
			const condition = args.filter.conditions[1];
			return 'notKeyword' in condition ? {ids : ["without-it"], total : 1} : {ids : ["with-it"], total : 1};
		});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");

		await jmap.toggleForAll({selectedFolder : "1::INBOX"}, "customFlag2");

		const setCall = capturedSets.find(c => Object.keys(c.update).includes("without-it"));
		const removeCall = capturedSets.find(c => Object.keys(c.update).includes("with-it"));
		assert.equal(setCall.update["without-it"]["keywords/$customflag2"], true);
		assert.equal(setCall.update["without-it"]["keywords/$flagged"], true);
		assert.equal(setCall.update["without-it"]["keywords/$customflag1"], null, "the 'set' side clears every OTHER custom flag");

		assert.equal(removeCall.update["with-it"]["keywords/$customflag2"], null);
		assert.equal(removeCall.update["with-it"]["keywords/$flagged"], null);
		assert.isUndefined(removeCall.update["with-it"]["keywords/$customflag1"],
			"the 'remove' side must NOT touch other custom flags - only setCustomFlag()'s own single-message path does that on set");
	});

	it("throws when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.toggleForAll({selectedFolder : "1::INBOX"}, "label1");
		}
		catch (e)
		{
			threw = true;
			assert.instanceOf(e, JmapUserError);
		}
		assert.isTrue(threw);
	});
});

describe("MailJmap.clearLabelsForAll()", () =>
{
	it("clears all 5 built-in labels plus configured custom labels for every matching id", async() =>
	{
		const jmap = new MailJmap(createFakeApp({Urgent : {name : "Urgent"}}));
		const {client, capturedSets} = createQueryThenSetFakeClient(() => ({ids : ["a", "b"], total : 2}));
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");

		await jmap.clearLabelsForAll({selectedFolder : "1::INBOX"});

		assert.equal(capturedSets.length, 1);
		const expectedPatch = {
			"keywords/$label1" : null, "keywords/$label2" : null, "keywords/$label3" : null,
			"keywords/$label4" : null, "keywords/$label5" : null, "keywords/$urgent" : null,
		};
		assert.deepEqual(capturedSets[0].update.a, expectedPatch);
		assert.deepEqual(capturedSets[0].update.b, expectedPatch);
	});

	it("throws when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.clearLabelsForAll({selectedFolder : "1::INBOX"});
		}
		catch (e)
		{
			threw = true;
			assert.instanceOf(e, JmapUserError);
		}
		assert.isTrue(threw);
	});
});
