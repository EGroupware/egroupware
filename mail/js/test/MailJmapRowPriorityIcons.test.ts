import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * PR #304: the row-list priority icon/class (mail_ui::header2gridelements()'s old
 * prio_high/prio_low mark, driven by the X-Priority header) had no equivalent at all in
 * email2row(), the JMAP-native row builder that replaced header2gridelements() - lost together
 * with get_rows() during the mail-list's port to client-side JMAP rendering. Restored via
 * MailJmap.rowPriority() (below 3 is high, above 3 is low, 3/missing/unparsable is normal,
 * matching the classic mapping), surfaced as both a `priority_icon` field and a `prio_high` row
 * class - the local IMAP shim already serves the underlying header inside its existing per-row
 * fetch (Api\Mail\Jmap\Imap::PRIORITY_HEADER_PROPERTY), so this is no extra IMAP round trip.
 */

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	config: (_name : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {
		egw,
		getCustomLabels: () => ({}),
		updateCustomLabelStylesheet: () => {},
		getRowLabelTags: (_flags : Record<string, string>) => []
	} as unknown as MailApp;
}

function fakeEmail(overrides : Record<string, any> = {}) : Record<string, any>
{
	return {
		id: "email1",
		subject: "Test subject",
		from: [{name: "Sender", email: "sender@example.com"}],
		to: [],
		cc: [],
		bcc: [],
		keywords: {},
		sentAt: "2026-01-01T00:00:00Z",
		receivedAt: "2026-01-01T00:00:00Z",
		size: 100,
		preview: "a body snippet",
		hasAttachment: false,
		...overrides
	};
}

function rowFor(priorityHeader? : string) : any
{
	const jmap = new MailJmap(createFakeApp());
	const email = fakeEmail(priorityHeader === undefined ? {} : {"header:X-Priority": priorityHeader});
	return (jmap as any).email2row(email, "1", "mbox1");
}

describe("MailJmap.email2row() - priority (X-Priority) row icon/class", () =>
{
	it("marks priority 1 (Highest) as high", () =>
	{
		const row = rowFor("1 (Highest)");
		assert.equal(row.priority_icon, "prio_high");
		assert.include(row.class.split(' '), "prio_high");
	});

	it("marks a bare priority 2 as high", () =>
	{
		const row = rowFor("2");
		assert.equal(row.priority_icon, "prio_high");
	});

	it("marks priority 5 (Lowest) as low", () =>
	{
		const row = rowFor(" 5 (Lowest)");
		assert.equal(row.priority_icon, "prio_low");
		assert.notInclude(row.class.split(' '), "prio_high", "only 'high' gets its own row class, same as classic mail_ui");
	});

	it("treats priority 3 (Normal) as no mark at all", () =>
	{
		const row = rowFor("3");
		assert.equal(row.priority_icon, "");
		assert.notInclude(row.class.split(' '), "prio_high");
	});

	it("treats a missing X-Priority header as no mark", () =>
	{
		const row = rowFor(undefined);
		assert.equal(row.priority_icon, "");
	});

	it("treats an unparsable X-Priority value as no mark, not a crash", () =>
	{
		const row = rowFor("garbage");
		assert.equal(row.priority_icon, "");
	});
});

describe("MailJmap.emails2threadRow() - priority class survives keyword-based css rebuild", () =>
{
	function threadRowFor(priorityHeader? : string) : any
	{
		const jmap = new MailJmap(createFakeApp());
		const representative = fakeEmail(priorityHeader === undefined ? {} : {"header:X-Priority": priorityHeader});
		const members = [{id: "email1", keywords: {}}, {id: "email2", keywords: {}}];
		return (jmap as any).emails2threadRow(representative, members, "thread1", "1", "mbox1");
	}

	it("keeps the representative's high-priority class on the collapsed thread row", () =>
	{
		const row = threadRowFor("1");
		assert.include(row.class.split(' '), "prio_high",
			"keywordsToRowFlags() rebuilds `class` from the thread's aggregated keywords - the representative's own priority class must be re-added on top, not lost");
	});

	it("adds no priority class for a normal-priority representative", () =>
	{
		const row = threadRowFor("3");
		assert.notInclude(row.class.split(' '), "prio_high");
	});

	it("adds no priority class when the representative has no X-Priority header", () =>
	{
		const row = threadRowFor(undefined);
		assert.notInclude(row.class.split(' '), "prio_high");
	});
});
