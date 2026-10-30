import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #127061 (Birgit, 2026-10-28): an S/MIME-signed meeting invitation was shown as a plain
 * text invitation instead of the usual server-rendered Accept/Decline/Tentative buttons.
 *
 * isSpecialCase() true just means "ask ajax_resolveSpecialCaseBody() first" - for an ordinary
 * (unsigned or PGP-signed) calendar invite this is already harmless and unchanged by this fix: the
 * server-side Jmap\Imap::specialCaseType() has no match for a bare multipart/alternative or a
 * pgp-signature multipart/signed, so resolveSpecialCaseBody() already correctly returns null, and
 * the client already falls back to the classic, calendar-aware path from there.
 *
 * The REAL bug was server-side: specialCaseType()'s `multipart/signed` branch (api/src/Mail/Jmap/
 * Imap.php) returned 'smime' the instant it found an S/MIME (pkcs7) signature sub-part, without
 * ever checking whether the signed content itself was (or contained) an inline text/calendar part
 * - so resolveSpecialCaseBody() SUCCEEDED with a generic, calendar-unaware smime-rendered body
 * instead of returning null, and the client's own fallback mechanism never got a chance to kick in
 * at all. Fixed there with a Jmap\Imap::hasInlineCalendarPart() check mirroring
 * Mail::jmapHasCalendarPart()'s same inline/non-attachment/non-empty distinction.
 *
 * This file covers the CLIENT-side twin of that same fix, done here purely to avoid the now-
 * pointless round-trip for this one specific case (same spirit as ticket #126491's own concern
 * about wasted round-trips) - isSpecialCase()'s own dedicated `multipart/signed` branch had the
 * identical unconditional-true bug. A first attempt just added the same `!hasInlineCalendarPart()`
 * check as one more `&&` clause inside that existing disjunct - which doesn't actually work: when
 * that disjunct comes out false, the function falls through to its own generic recursive walk
 * (`subParts.some(isSpecialCase)`), which independently rediscovers the detached-signature
 * sub-part (application/pkcs7-signature is ITS OWN separate SPECIAL_CASE_TYPES member) and returns
 * true anyway, completely undoing the carve-out. The real fix makes the `multipart/signed` check
 * an early, DECISIVE return instead - it never falls through to the recursive walk at all.
 */

function createJmap() : MailJmap
{
	const egw : any = {lang : (s : string) => s};
	const app = {egw} as unknown as MailApp;
	return new MailJmap(app);
}

/** multipart/alternative with text/plain + text/html + an inline (non-attachment) text/calendar part - the common meeting-invite shape */
function calendarAlternative(overrides : Partial<any> = {}) : any
{
	return {
		type : 'multipart/alternative',
		subParts : [
			{type : 'text/plain', size : 50},
			{type : 'text/html', size : 100},
			{type : 'text/calendar', size : 300, disposition : 'inline', ...overrides},
		],
	};
}

function pkcs7Signature() : any
{
	return {type : 'application/pkcs7-signature', size : 1000};
}

function pgpSignature() : any
{
	return {type : 'application/pgp-signature', size : 500};
}

describe("MailJmap.isSpecialCase() with an S/MIME-signed meeting invite (ticket #127061)", () =>
{
	it("does NOT treat a signed meeting invite as a special case (falls through to the calendar-aware classic path)", () =>
	{
		const jmap = createJmap();
		const part = {type : 'multipart/signed', subParts : [calendarAlternative(), pkcs7Signature()]};

		assert.isFalse((jmap as any).isSpecialCase(part));
	});

	it("still treats a signed, non-calendar message as a special case (existing S/MIME behaviour preserved)", () =>
	{
		const jmap = createJmap();
		const part = {
			type : 'multipart/signed',
			subParts : [
				{type : 'multipart/alternative', subParts : [{type : 'text/plain', size : 50}, {type : 'text/html', size : 100}]},
				pkcs7Signature(),
			],
		};

		assert.isTrue((jmap as any).isSpecialCase(part));
	});

	it("still treats a signed message with only an ATTACHED .ics (not an inline invite) as a special case", () =>
	{
		const jmap = createJmap();
		const part = {
			type : 'multipart/signed',
			subParts : [
				{
					type : 'multipart/mixed',
					subParts : [
						{type : 'text/plain', size : 50},
						{type : 'text/calendar', size : 300, disposition : 'attachment', name : 'invite.ics'},
					],
				},
				pkcs7Signature(),
			],
		};

		assert.isTrue((jmap as any).isSpecialCase(part),
			"an attached .ics file is a plain attachment, not a meeting invite - the classic S/MIME handling must still apply");
	});

	it("a PGP-signed calendar invite is unaffected by this fix (pre-existing behaviour, still true)", () =>
	{
		// Not this fix's concern: isSmimeSignaturePart() is false for a pgp-signature sub-part, so
		// the new early-return branch never fires at all here - falls through to the ordinary
		// recursive walk, which finds the inline text/calendar part via the flat
		// SPECIAL_CASE_TYPES match (same mechanism, and same result, as the unsigned case below).
		// isSpecialCase() true here just means "ask the server first" - ajax_resolveSpecialCaseBody()
		// already correctly returns null for this (specialCaseType() has no multipart/signed match
		// without an S/MIME signature at all), and the client already falls back to the classic
		// calendar-aware path from there - unrelated to, and unchanged by, this fix.
		const jmap = createJmap();
		const part = {type : 'multipart/signed', subParts : [calendarAlternative(), pgpSignature()]};

		assert.isTrue((jmap as any).isSpecialCase(part));
	});

	it("an UNSIGNED meeting invite is unaffected by this fix (pre-existing behaviour, still true, ticket #126491)", () =>
	{
		// Same reasoning as the PGP case above: an inline text/calendar part matches the flat
		// SPECIAL_CASE_TYPES set directly (ticket #126491's own exclusion only applies to an
		// ATTACHED/empty one) - isSpecialCase() true here is the pre-existing, correct, unaffected
		// status quo; resolveSpecialCaseBody() already returns null for it server-side
		// (specialCaseType() has no match for a bare 'multipart/alternative'), falling back to the
		// classic calendar-aware path exactly as before.
		const jmap = createJmap();
		const part = calendarAlternative();

		assert.isTrue((jmap as any).isSpecialCase(part));
	});
});
