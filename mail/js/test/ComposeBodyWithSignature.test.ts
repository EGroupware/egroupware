import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

const HTML_SIG = "<p>John Doe</p>";
const TEXT_SIG = "John Doe";
const NO_SIG = {htmlSignature: "", textSignature: ""};
const SIG = {htmlSignature: HTML_SIG, textSignature: TEXT_SIG};

describe("MailJmap.composeBodyWithSignature() - html mode", () =>
{
	it("appends below an empty new-message body, with the ruler and a leading blank line", () =>
	{
		const result = MailJmap.composeBodyWithSignature("", "html", SIG, {placement: "below"});
		assert.equal(result,
			'<p><br/></p>\n' + '<div id="mail-compose-signature">' +
			'<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG + '</div>');
	});

	it("does not add a leading blank line above an existing new-message body (not a reply)", () =>
	{
		const result = MailJmap.composeBodyWithSignature("existing body", "html", SIG, {placement: "below"});
		assert.equal(result,
			'existing body' + '<div id="mail-compose-signature">' +
			'<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG + '</div>');
	});

	it("adds the leading blank line above a reply's quoted body even though it's non-empty", () =>
	{
		const result = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "below", isReply: true});
		assert.equal(result,
			'<p><br/></p>\n' + 'quoted' + '<div id="mail-compose-signature">' +
			'<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG + '</div>');
	});

	it("places the signature above the body when placement is 'top'", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "top", isReply: true});
		// the leading blank line stays OUTSIDE the signature marker div - nesting it inside let a
		// user's own typing (into that line, the only obviously-clickable spot in a fresh 'top'
		// compose) get deleted along with the old signature on the next identity switch, since
		// updateSignatureForIdentity()'s removal deletes the whole marker div (found live
		// 2026-09-04, doc/ai/projects/mail-compose-jmap-migration.md's own entry for that date)
		assert.equal(result,
			'<p><br/></p>\n' + '<div id="mail-compose-signature">' +
			'<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG + '</div>' + 'body');
	});

	it("omits the <hr> ruler when disableRuler is set", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "below", disableRuler: true});
		assert.equal(result, 'body' + '<div id="mail-compose-signature">' + HTML_SIG + '</div>');
	});

	it("does nothing (returns body unchanged) when placement is 'none'", () =>
	{
		assert.equal(MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "none"}), "body");
	});

	it("does nothing when the identity has no HTML signature, even if placement isn't 'none'", () =>
	{
		assert.equal(MailJmap.composeBodyWithSignature("body", "html", NO_SIG, {placement: "below"}), "body");
	});

	it("still adds the leading blank line above a reply's quoted body when placement is 'none' (signature deferred to send-time)", () =>
	{
		// insertSignatureAtTopOfMessage's 'no_belowaftersend' - found live 2026-09-22 (Ingo): a
		// reply with this preference left the quoted text glued to the top of the editor
		const result = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "none", isReply: true});
		assert.equal(result, '<p><br/></p>\n' + 'quoted');
	});

	it("still adds the leading blank line above a reply's quoted body when the identity has no signature at all", () =>
	{
		const result = MailJmap.composeBodyWithSignature("quoted", "html", NO_SIG, {placement: "below", isReply: true});
		assert.equal(result, '<p><br/></p>\n' + 'quoted');
	});

	it("does NOT add a leading blank line for a non-reply new compose when placement is 'none'", () =>
	{
		assert.equal(MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "none"}), "body");
	});

	it("defaults the leading blank line to a <p>, same as omitting formatBlock entirely", () =>
	{
		const withDefault = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "below", isReply: true});
		const withExplicitP = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "below", isReply: true, formatBlock: "p"});
		assert.equal(withDefault, withExplicitP);
		assert.isTrue(withDefault.startsWith('<p><br/></p>\n'));
	});

	it("uses a <div> for the leading blank line when formatBlock is 'div' (rte_formatblock=customparagraph, aka 'Small Paragraph')", () =>
	{
		// ralf, 2026-09-22: a mismatched <p> inserted while the editor's own forced_root_block is
		// 'div' is foreign structure TinyMCE never generates itself - exactly the kind of thing its
		// own DOM re-serialization could merge/collapse away differently than a same-typed empty
		// block, on top of the already-fixed 'no_belowaftersend' bug this generalizes.
		const result = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "below", isReply: true, formatBlock: "div"});
		assert.equal(result,
			'<div><br/></div>\n' + 'quoted' + '<div id="mail-compose-signature">' +
			'<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG + '</div>');
		assert.notInclude(result, '<p><br/></p>', "must not ALSO emit the <p> variant");
	});

	it("uses a <div> for the leading blank line when placement is 'none' too (signature deferred to send-time)", () =>
	{
		const result = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "none", isReply: true, formatBlock: "div"});
		assert.equal(result, '<div><br/></div>\n' + 'quoted');
	});
});

describe("MailJmap.composeBodyWithSignature() - plain-text mode", () =>
{
	it("uses the RFC-conventional '-- ' sig-dashes separator below the body by default", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "plain", SIG, {placement: "below"});
		assert.equal(result, 'body' + '\r\n-- \r\n' + TEXT_SIG);
	});

	it("uses a plain blank line instead of the sig-dashes when disableRuler is set", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "plain", SIG, {placement: "below", disableRuler: true});
		assert.equal(result, 'body' + '\r\n' + TEXT_SIG);
	});

	it("uses the plain-text signature variant, not the HTML one", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "plain", SIG, {placement: "below", disableRuler: true});
		assert.notInclude(result, "<p>");
	});

	it("still adds the leading blank line above a reply's quoted body when placement is 'none'", () =>
	{
		const result = MailJmap.composeBodyWithSignature("quoted", "plain", SIG, {placement: "none", isReply: true});
		assert.equal(result, '\r\n' + 'quoted');
	});
});

/**
 * Full enumeration of all THREE mail/common preferences this function's behaviour depends on -
 * requested by ralf after the 2026-09-22 fix (Ingo's reply + 'no_belowaftersend' repro) to make
 * sure that fix (a reply always gets its leading blank line, independent of signature placement/
 * timing) holds across every combination, not just the one preference value that was actually
 * reported broken, and extended (same session) to cover the 'formatBlock' generalization too:
 * - insertSignatureAtTopOfMessage ('1' top / '0' below / 'no_belowaftersend' none) -> `placement`
 * - disableRulerForSignatureSeparation (show/hide the separator) -> `disableRuler`
 * - rte_formatblock=customparagraph aka "Small Paragraph" (normalizeFormatBlock() -> 'div',
 *   'p' otherwise) -> `formatBlock`
 * crossed with isReply (true/false) and mimeType (html/plain; formatBlock only affects html mode,
 * plain mode has no tags at all). Each case builds its own expected string independently from
 * this function's documented contract (its own docblock), not by mirroring the implementation, so
 * this is a real specification check, not a tautology.
 */
describe("MailJmap.composeBodyWithSignature() - full preference-matrix enumeration", () =>
{
	const PLACEMENTS: Array<'top' | 'below' | 'none'> = ['top', 'below', 'none'];
	const BODY = 'quoted';

	for (const mimeType of ['html', 'plain'] as const)
	{
		const sigSource = mimeType === 'html' ? HTML_SIG : TEXT_SIG;
		// formatBlock only matters for html mode (plain-text has no tags) - a single-element loop
		// for plain mode keeps the nesting uniform below without doubling up meaningless cases.
		const formatBlocks = mimeType === 'html' ? ['p', 'div'] as const : ['p'] as const;

		for (const formatBlock of formatBlocks)
		{
			const startLine = mimeType === 'html' ? `<${formatBlock}><br/></${formatBlock}>\n` : '\r\n';

			for (const placement of PLACEMENTS)
			{
				for (const disableRuler of [false, true])
				{
					for (const isReply of [false, true])
					{
						const label = `mimeType=${mimeType} formatBlock=${formatBlock} placement=${placement} disableRuler=${disableRuler} isReply=${isReply}`;

						// For placement 'top', plain-text mode's own separator ('before') ALSO starts
						// with '\r\n' (same bytes as the leading blank `startLine`) - a bare
						// `startsWith(startLine)` can't distinguish "the blank line was inserted" from
						// "the separator just happens to start the same way" in that one combination, so
						// the iff-check below only runs where that ambiguity doesn't exist. isReply=true
						// is still fully covered unambiguously by the dedicated invariant test below,
						// which doesn't rely on comparing against the isReply=false case at all.
						if (placement !== 'top')
						{
							it(`${label}: leading blank line present iff isReply`, () =>
							{
								const result = MailJmap.composeBodyWithSignature(BODY, mimeType, SIG, {placement, disableRuler, isReply, formatBlock});
								// BODY is always non-empty here, so per the docblock ("never adds an empty
								// leading line above an existing quoted body" is the OLD, now-fixed
								// behaviour) the blank line is present exactly when isReply is true -
								// regardless of placement/disableRuler/formatBlock, which is the whole
								// point of the fix (and its generalization to formatBlock).
								assert.equal(result.startsWith(startLine), isReply, label);
							});
						}

						it(`${label}: signature block present iff placement isn't 'none'`, () =>
						{
							const result = MailJmap.composeBodyWithSignature(BODY, mimeType, SIG, {placement, disableRuler, isReply, formatBlock});
							assert.equal(result.includes(sigSource), placement !== 'none', label);
						});

						it(`${label}: separator/ruler present iff placement isn't 'none' and disableRuler isn't set`, () =>
						{
							const result = MailJmap.composeBodyWithSignature(BODY, mimeType, SIG, {placement, disableRuler, isReply, formatBlock});
							const separator = mimeType === 'html' ? 'class="ruler"' : '-- ';
							assert.equal(result.includes(separator), placement !== 'none' && !disableRuler, label);
						});

						it(`${label}: original body always survives unchanged`, () =>
						{
							const result = MailJmap.composeBodyWithSignature(BODY, mimeType, SIG, {placement, disableRuler, isReply, formatBlock});
							assert.include(result, BODY, label);
						});

						if (mimeType === 'html')
						{
							it(`${label}: never emits the OTHER format block's tag`, () =>
							{
								const result = MailJmap.composeBodyWithSignature(BODY, mimeType, SIG, {placement, disableRuler, isReply, formatBlock});
								const otherTag = formatBlock === 'div' ? '<p><br/></p>' : '<div><br/></div>';
								assert.notInclude(result, otherTag, label);
							});
						}
					}
				}
			}
		}
	}

	it("the fix's key invariant: EVERY reply keeps its leading blank line, across all 24 placement×disableRuler×formatBlock×mimeType combinations", () =>
	{
		for (const mimeType of ['html', 'plain'] as const)
		{
			const formatBlocks = mimeType === 'html' ? ['p', 'div'] as const : ['p'] as const;
			for (const formatBlock of formatBlocks)
			{
				const startLine = mimeType === 'html' ? `<${formatBlock}><br/></${formatBlock}>\n` : '\r\n';
				for (const placement of PLACEMENTS)
				{
					for (const disableRuler of [false, true])
					{
						const result = MailJmap.composeBodyWithSignature(BODY, mimeType, SIG, {placement, disableRuler, isReply: true, formatBlock});
						assert.isTrue(result.startsWith(startLine),
							`mimeType=${mimeType} formatBlock=${formatBlock} placement=${placement} disableRuler=${disableRuler} isReply=true should keep the leading blank line`);
					}
				}
			}
		}
	});
});
