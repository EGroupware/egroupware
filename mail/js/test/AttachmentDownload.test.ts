import {assert} from "@open-wc/testing";
import {attachmentSaveUrl, downloadAttachments} from "../attachmentDownload";

/**
 * Test downloadAttachments() - the "Download all attachments" loop that saves every attachment
 * of a message as its own file (as opposed to "Save as ZIP", one server-assembled archive).
 *
 * Behaviour under test:
 * 1. Per attachment, the same two paths a single "Download" uses: JMAP blob download when the
 *    row has a blobId and its mail_id parses, else a fetch of the classic
 *    mail.mail_ui.getAttachment URL saved from the resulting blob.
 * 2. Downloads run strictly sequentially - never overlapping - with a gap between them.
 * 3. One failing attachment does not abort the rest; its filename comes back in `failed`.
 * 4. Rows without a filename (the null holes an attachmentsBlock can contain) are skipped.
 *
 * Setup strategy: every side effect is injected (`fetch`, `saveBlob`, `delay`, plus a stub
 * `jmap`), so nothing here touches the network, the session or the real DOM download path.
 * `delay` resolves immediately but is recorded, keeping the tests fast while still asserting
 * the pacing calls happen. Ordering/overlap is proven by a shared `events` log: a "start"/"end"
 * pair is pushed around each simulated download, so an interleaved pair would be visible.
 *
 * Pass criteria are asserted explicitly per test (which path ran for which row, the exact
 * event order, the returned counts). A failure means the loop's dispatch/sequencing/error
 * handling changed - none of it is environment-sensitive.
 */

interface Recorded
{
	events: string[];
	fetched: string[];
	saved: {filename: string, size: number}[];
	jmapCalls: {profileID: string, blobId: string, filename: string}[];
	delays: number[];
}

const egw = {
	webserverUrl: "/egroupware",
};

/**
 * Deps with every side effect recorded. `failFor` names filenames whose download should
 * reject, `blobIdOf` decides which rows are JMAP-capable (default: any row with a blobId),
 * and `unparseable` names mail_ids messageReference() refuses - the classic-fallback case.
 */
function recordingDeps(options: {failFor?: string[], unparseable?: string[]} = {})
{
	const rec: Recorded = {events: [], fetched: [], saved: [], jmapCalls: [], delays: []};
	const failFor = options.failFor ?? [];
	const unparseable = options.unparseable ?? [];

	const deps = {
		egw,
		jmap: {
			messageReference: (mail_id: string) =>
			{
				if (unparseable.includes(mail_id))
				{
					throw new Error("unparseable row-id");
				}
				return {profileID: "1"};
			},
			downloadAttachment: async (profileID: string, blobId: string, filename: string) =>
			{
				rec.events.push("jmap start " + filename);
				rec.jmapCalls.push({profileID, blobId, filename});
				await Promise.resolve();
				if (failFor.includes(filename))
				{
					rec.events.push("jmap fail " + filename);
					throw new Error("JMAP download failed");
				}
				rec.events.push("jmap end " + filename);
			},
		},
		fetch: (async (url: string) =>
		{
			const filename = classicFilename(url);
			rec.events.push("fetch start " + filename);
			rec.fetched.push(url);
			await Promise.resolve();
			if (failFor.includes(filename))
			{
				rec.events.push("fetch fail " + filename);
				return {ok: false, status: 404, statusText: "Not Found"};
			}
			rec.events.push("fetch end " + filename);
			return {ok: true, status: 200, statusText: "OK", blob: async () => new Blob(["x"])};
		}) as any,
		saveBlob: (blob: Blob, filename: string) =>
		{
			rec.events.push("save " + filename);
			rec.saved.push({filename, size: blob.size});
		},
		delay: async (ms: number) =>
		{
			rec.delays.push(ms);
		},
	};
	return {deps, rec};
}

// the classic URL carries no filename, so the fetch stub above cannot tell rows apart by
// itself - classicRow() puts the filename in the row-id's uid part, which does end up in the
// URL (as id=), and classicFilename() reads it back out
function classicRow(filename: string)
{
	return {filename, mail_id: "1::INBOX::" + filename, partID: "2"};
}

function classicFilename(url: string): string
{
	const id = new URL(url, "http://localhost").searchParams.get("id") ?? "";
	return id.split("::").pop() || url;
}

function jmapRow(filename: string, mail_id = "1::INBOX::12")
{
	return {filename, mail_id, partID: "2", blobId: "blob-" + filename, type: "application/pdf"};
}

describe("downloadAttachments()", () =>
{
	it("downloads nothing for an empty or filename-less list", async () =>
	{
		const {deps, rec} = recordingDeps();

		assert.deepEqual(await downloadAttachments([], deps), {downloaded: 0, failed: []});
		// the null holes an attachmentsBlock can contain must not be attempted
		assert.deepEqual(await downloadAttachments([null, undefined, {} as any] as any, deps),
			{downloaded: 0, failed: []});
		assert.deepEqual(rec.events, []);
		assert.deepEqual(rec.delays, []);
	});

	it("uses the JMAP blob path for rows with a blobId", async () =>
	{
		const {deps, rec} = recordingDeps();

		const result = await downloadAttachments([jmapRow("one.pdf"), jmapRow("two.pdf")], deps);

		assert.deepEqual(result, {downloaded: 2, failed: []});
		assert.deepEqual(rec.jmapCalls, [
			{profileID: "1", blobId: "blob-one.pdf", filename: "one.pdf"},
			{profileID: "1", blobId: "blob-two.pdf", filename: "two.pdf"},
		]);
		// jmap.downloadAttachment() saves the file itself - no fetch, no saveBlob here
		assert.deepEqual(rec.fetched, []);
		assert.deepEqual(rec.saved, []);
	});

	it("fetches and saves rows without a blobId", async () =>
	{
		const {deps, rec} = recordingDeps();

		const result = await downloadAttachments([classicRow("plain.txt")], deps);

		assert.deepEqual(result, {downloaded: 1, failed: []});
		assert.equal(rec.fetched.length, 1);
		assert.include(rec.fetched[0], "menuaction=mail.mail_ui.getAttachment");
		assert.include(rec.fetched[0], "mode=save");
		assert.deepEqual(rec.saved, [{filename: "plain.txt", size: 1}]);
		assert.deepEqual(rec.jmapCalls, []);
	});

	it("falls back to the classic path when the row-id does not parse", async () =>
	{
		const {deps, rec} = recordingDeps({unparseable: ["broken-id"]});

		const result = await downloadAttachments([jmapRow("one.pdf", "broken-id")], deps);

		assert.deepEqual(result, {downloaded: 1, failed: []});
		assert.deepEqual(rec.jmapCalls, []);
		assert.equal(rec.fetched.length, 1);
		assert.deepEqual(rec.saved, [{filename: "one.pdf", size: 1}]);
	});

	it("falls back to the classic path when no jmap access is available at all", async () =>
	{
		const {deps, rec} = recordingDeps();

		const result = await downloadAttachments([jmapRow("one.pdf")], {...deps, jmap: undefined});

		assert.deepEqual(result, {downloaded: 1, failed: []});
		assert.deepEqual(rec.jmapCalls, []);
		assert.equal(rec.fetched.length, 1);
	});

	it("downloads sequentially, with a gap between files and none before the first", async () =>
	{
		const {deps, rec} = recordingDeps();

		await downloadAttachments([jmapRow("one.pdf"), classicRow("two.txt"), jmapRow("three.pdf")], deps);

		// each start is followed by its own end - no interleaving of two downloads
		assert.deepEqual(rec.events, [
			"jmap start one.pdf", "jmap end one.pdf",
			"fetch start two.txt", "fetch end two.txt", "save two.txt",
			"jmap start three.pdf", "jmap end three.pdf",
		]);
		// 3 files -> 2 gaps: paced between downloads, but never delaying the first one
		assert.equal(rec.delays.length, 2);
		assert.isTrue(rec.delays.every(ms => ms > 0), "gap must be a positive number of ms");
	});

	it("keeps going after a failure and reports the failing filenames", async () =>
	{
		const {deps, rec} = recordingDeps({failFor: ["bad.pdf", "alsobad.txt"]});

		const result = await downloadAttachments([
			jmapRow("good.pdf"), jmapRow("bad.pdf"), classicRow("alsobad.txt"), classicRow("good.txt"),
		], deps);

		assert.deepEqual(result, {downloaded: 2, failed: ["bad.pdf", "alsobad.txt"]});
		// the failing HTTP fetch must not have been saved, the succeeding one must
		assert.deepEqual(rec.saved, [{filename: "good.txt", size: 1}]);
		assert.include(rec.events, "jmap start good.pdf");
		assert.include(rec.events, "fetch start good.txt");
	});
});

describe("attachmentSaveUrl()", () =>
{
	it("builds the classic per-attachment save URL", () =>
	{
		const url = attachmentSaveUrl(egw, {
			filename: "one.pdf", mail_id: "1::INBOX::12", partID: "2", winmailFlag: false, smime_type: "",
		});

		assert.isTrue(url.startsWith("/egroupware/index.php?"), "must be webserverUrl-based: " + url);
		const params = new URL(url, "http://localhost").searchParams;
		assert.equal(params.get("menuaction"), "mail.mail_ui.getAttachment");
		assert.equal(params.get("mode"), "save");
		assert.equal(params.get("id"), "1::INBOX::12");
		assert.equal(params.get("part"), "2");
	});
});
