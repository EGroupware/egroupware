import {assert} from "@open-wc/testing";
import {dropRtfBody, findTnefEntry, isRtfBody, isTnefEntry, renumber, spliceUnpacked} from "../tnef";

/**
 * Rules for a winmail.dat (TNEF), see tnef.ts - ticket #126161: the preview unpacked the winmail.dat of a mail, but the
 * display popup (and an .eml opened from a ticket) showed just "winmail.dat", and the unpacked list contained
 * "Untitled.rtf", the RTF copy of the mail body.
 */
describe("isTnefEntry / findTnefEntry", () =>
{
	it("recognizes a winmail.dat by its type or name, ignoring the case", () =>
	{
		assert.isTrue(isTnefEntry({filename: "winmail.dat", type: "application/ms-tnef"}));
		assert.isTrue(isTnefEntry({filename: "Winmail.DAT"}));
		assert.isTrue(isTnefEntry({name: "winmail.dat"}), "JMAP metadata uses name");
		assert.isTrue(isTnefEntry({type: "application/ms-tnef"}));
		assert.isTrue(isTnefEntry({type: "Application/MS-TNEF"}));
		assert.isTrue(isTnefEntry({type: "application/vnd.ms-tnef"}));
	});

	it("does NOT depend on the winmailFlag, which the JMAP code leaves empty for the raw winmail.dat", () =>
	{
		assert.isTrue(isTnefEntry({filename: "winmail.dat", type: "application/ms-tnef", winmailFlag: null, blobId: "b1"}));
	});

	it("does not take the label in mimetype for the type", () =>
	{
		assert.isFalse(isTnefEntry({filename: "a.pdf", type: "application/pdf", mimetype: "application/ms-tnef"}));
	});

	it("is false for other attachments and for nothing", () =>
	{
		assert.isFalse(isTnefEntry({filename: "Rechnung.pdf", type: "application/pdf"}));
		assert.isFalse(isTnefEntry({filename: "winmail.dat.txt", type: "text/plain"}));
		assert.isFalse(isTnefEntry({}));
		assert.isFalse(isTnefEntry(null));
		assert.isFalse(isTnefEntry(undefined));
	});

	it("finds the winmail.dat in any position, not just as first attachment", () =>
	{
		assert.equal(findTnefEntry([{filename: "a.pdf"}, {filename: "b.png"}, {filename: "winmail.dat"}]), 2);
		assert.equal(findTnefEntry([{filename: "winmail.dat"}, {filename: "a.pdf"}]), 0);
		assert.equal(findTnefEntry([{filename: "a.pdf"}]), -1);
		assert.equal(findTnefEntry([]), -1);
		assert.equal(findTnefEntry(null), -1);
		assert.equal(findTnefEntry(<any>"not an array"), -1);
		assert.equal(findTnefEntry([null, {filename: "winmail.dat"}]), 1, "holes in the list");
	});
});

describe("dropRtfBody", () =>
{
	const rtf = {filename: "Untitled.rtf", type: "application/rtf", size: "528 B"};
	const pdf = {filename: "Rechnung 174977 Werner Mitschele GmbH.pdf", type: "application/pdf", size: "86.12k"};

	it("removes the RTF copy of the mail body, if there are other files (the mail of the ticket)", () =>
	{
		assert.deepEqual(dropRtfBody([rtf, pdf]), [pdf]);
		assert.deepEqual(dropRtfBody([pdf, rtf]), [pdf]);
	});

	it("keeps the RTF, if it is all there is: it might be the only copy of the body", () =>
	{
		assert.deepEqual(dropRtfBody([rtf]), [rtf]);
		assert.deepEqual(dropRtfBody([]), []);
	});

	it("recognizes the RTF by its name AND an RTF type (or no type) only", () =>
	{
		assert.isTrue(isRtfBody({filename: "untitled.RTF", type: "text/rtf"}));
		assert.isTrue(isRtfBody({name: "Untitled.rtf"}));
		assert.isTrue(isRtfBody({filename: "Untitled.rtf", type: "application/x-rtf"}));
		assert.isFalse(isRtfBody({filename: "Untitled.rtf", type: "application/pdf"}), "a PDF with that name is no RTF body");
		assert.isFalse(isRtfBody({filename: "Brief.rtf", type: "application/rtf"}), "a real RTF document of the user");
		assert.isFalse(isRtfBody(null));
		assert.deepEqual(dropRtfBody([{filename: "Brief.rtf", type: "application/rtf"}, pdf]).length, 2);
	});

	it("does not change the given array", () =>
	{
		const list = [rtf, pdf];
		dropRtfBody(list);
		assert.deepEqual(list, [rtf, pdf]);
	});
});

describe("spliceUnpacked / renumber", () =>
{
	it("replaces the winmail.dat by the unpacked files between the other attachments and renumbers them", () =>
	{
		const merged = spliceUnpacked(
			[{filename: "first.pdf", attachment_number: 0}, {filename: "winmail.dat", attachment_number: 1}, {filename: "last.png", attachment_number: 2}],
			1, [{filename: "a.doc"}, {filename: "b.xls"}]);
		assert.deepEqual(merged.map(a => a.filename), ["first.pdf", "a.doc", "b.xls", "last.png"]);
		assert.deepEqual(merged.map(a => a.attachment_number), [0, 1, 2, 3]);
	});

	it("works for a winmail.dat as only attachment and for the first and last position", () =>
	{
		assert.deepEqual(spliceUnpacked([{filename: "winmail.dat"}], 0, [{filename: "x"}]).map(a => a.attachment_number), [0]);
		assert.deepEqual(spliceUnpacked([{filename: "winmail.dat"}, {filename: "b"}], 0, [{filename: "x"}, {filename: "y"}]).map(a => a.filename), ["x", "y", "b"]);
		assert.deepEqual(spliceUnpacked([{filename: "a"}, {filename: "winmail.dat"}], 1, [{filename: "x"}]).map(a => a.filename), ["a", "x"]);
	});

	it("does not change the given array", () =>
	{
		const list = [{filename: "a"}, {filename: "winmail.dat"}];
		spliceUnpacked(list, 1, [{filename: "x"}]);
		assert.deepEqual(list.map(a => a.filename), ["a", "winmail.dat"]);
	});

	it("renumber skips holes", () =>
	{
		const list : any[] = [{filename: "a"}, null, {filename: "c"}];
		assert.deepEqual(renumber(list).map(a => a?.attachment_number), [0, undefined, 2]);
	});
});
