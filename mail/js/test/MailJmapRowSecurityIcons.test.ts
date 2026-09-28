import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #125281: the row-list S/MIME icon (mail_ui::header2gridelements()'s old
 * `$data['smime'] = Smime::TYPE_SIGN|TYPE_ENCRYPT`) had no equivalent at all in email2row(), the
 * JMAP-native row builder that replaced header2gridelements() (mail/js/jmap.ts) - a plain
 * oversight during that migration (commit 1d9ceed63a removed the classic code, nothing ported
 * the field). Restored, plus the same detection-only (no signature verification) treatment for
 * PGP/MIME (RFC 3156), both driven by the SAME top-level Content-Type header already fetched for
 * every list row - no extra per-row cost either way.
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

function rowFor(contentTypeHeader? : string) : any
{
	const jmap = new MailJmap(createFakeApp());
	const email = fakeEmail(contentTypeHeader === undefined ? {} : {"header:content-type": contentTypeHeader});
	return (jmap as any).email2row(email, "1", "mbox1");
}

describe("MailJmap.email2row() - security (S/MIME/PGP) row icons", () =>
{
	it("shows the S/MIME signed icon for a detached pkcs7 signature", () =>
	{
		const row = rowFor('multipart/signed; protocol="application/pkcs7-signature"; micalg=sha-256');
		assert.equal(row.smime, "smime_sign");
		assert.equal(row.pgp, "");
	});

	it("shows the S/MIME encrypted icon for an opaque pkcs7-mime message", () =>
	{
		const row = rowFor("application/pkcs7-mime; smime-type=enveloped-data");
		assert.equal(row.smime, "smime_encrypt");
	});

	it("shows the PGP icon for a PGP/MIME detached signature", () =>
	{
		const row = rowFor('multipart/signed; protocol="application/pgp-signature"; micalg=pgp-sha256');
		assert.equal(row.pgp, "envelope-at-fill");
		assert.equal(row.smime, "", "must not also claim S/MIME - it's a different protocol param");
	});

	it("shows the PGP icon for PGP/MIME encryption", () =>
	{
		const row = rowFor('multipart/encrypted; protocol="application/pgp-encrypted"');
		assert.equal(row.pgp, "envelope-at-fill");
	});

	it("shows neither icon for a plain message", () =>
	{
		const row = rowFor("text/plain; charset=utf-8");
		assert.equal(row.smime, "");
		assert.equal(row.pgp, "");
	});

	it("shows neither icon when there is no Content-Type header at all", () =>
	{
		const row = rowFor(undefined);
		assert.equal(row.smime, "");
		assert.equal(row.pgp, "");
	});
});
