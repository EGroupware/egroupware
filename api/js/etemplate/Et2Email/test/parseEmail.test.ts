import {assert} from "@open-wc/testing";
import {parseEmail, stripEmoji} from "../utils";

/**
 * Contract: parseEmail() picks the first and last name used for a letter avatar's initials from
 * the words of the display name, and emoji never count as a word.
 *
 * Why it matters: an emoji in front of a sender name ("📦 DHL Paketankündigung") became the first
 * name, so the letter avatar showed a broken character plus one letter instead of two initials.
 *
 * Pass criteria: emoji (including joined sequences, flags and keycaps) are skipped wherever they
 * are in the name, names without emoji parse as before, and the label keeps the name as sent.
 *
 * Environment: no network, no egw object.
 */
describe("parseEmail()", () =>
{
	const cases : [string, string, string, string][] = [
		["plain name", "Ralf Becker <rb@egroupware.org>", "Ralf", "Becker"],
		["leading emoji", "📦 DHL Paketankündigung (dhl.de) <noreply@dhl.de>", "DHL", "Paketankündigung"],
		["emoji glued to word", "📦DHL Paketankündigung <noreply@dhl.de>", "DHL", "Paketankündigung"],
		["emoji between words", "Jane ❤️ Doe <jane@example.org>", "Jane", "Doe"],
		["joined emoji sequence", "👨‍👩‍👧 Family Office <family@example.org>", "Family", "Office"],
		["flag and keycap", "🇩🇪 1️⃣ Erste Bank <bank@example.org>", "Erste", "Bank"],
		["title then emoji", "Dr. 👨‍⚕️ Max Muster <max@example.org>", "Max", "Muster"],
		["digits are kept", "1und1 Service <service@example.org>", "1und1", "Service"]
	];
	cases.forEach(([name, address, fname, lname]) =>
	{
		it(name, () =>
		{
			const parsed = parseEmail(address);
			assert.equal(parsed.fname, fname);
			assert.equal(parsed.lname, lname);
		});
	});

	it("keeps emoji in the label", () =>
	{
		assert.equal(parseEmail("📦 DHL Paketankündigung <noreply@dhl.de>").label, "📦 DHL Paketankündigung");
	});

	it("falls back to the email for a name of only emoji", () =>
	{
		const parsed = parseEmail("🎉🎉 <party.time@example.org>");
		assert.equal(parsed.fname, "party");
	});
});

describe("stripEmoji()", () =>
{
	it("leaves text without emoji alone", () =>
	{
		assert.equal(stripEmoji("Jörg Müller-Lüdenscheidt 123 #1"), "Jörg Müller-Lüdenscheidt 123 #1");
	});

	it("returns an empty string for only emoji", () =>
	{
		assert.equal(stripEmoji("🎉 👍🏽 🏴󠁧󠁢󠁳󠁣󠁴󠁿"), "");
	});
});
