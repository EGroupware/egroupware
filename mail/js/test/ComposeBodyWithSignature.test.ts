import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

const HTML_SIG = "<p>John Doe</p>";
const TEXT_SIG = "John Doe";
const NO_SIG = {htmlSignature: "", textSignature: ""};
const SIG = {htmlSignature: HTML_SIG, textSignature: TEXT_SIG};

describe("MailJmap.composeBodyWithSignature() - html mode", () =>
{
	it("appends below a new-message body, with the ruler, no leading blank line since body is empty", () =>
	{
		const result = MailJmap.composeBodyWithSignature("", "html", SIG, {placement: "below"});
		assert.equal(result,
			'<p><br/></p>\n' + '' + '<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG);
	});

	it("does not add a leading blank line above an existing new-message body (not a reply)", () =>
	{
		const result = MailJmap.composeBodyWithSignature("existing body", "html", SIG, {placement: "below"});
		assert.equal(result,
			'existing body' + '<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG);
	});

	it("adds the leading blank line above a reply's quoted body even though it's non-empty", () =>
	{
		const result = MailJmap.composeBodyWithSignature("quoted", "html", SIG, {placement: "below", isReply: true});
		assert.equal(result,
			'<p><br/></p>\n' + 'quoted' + '<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG);
	});

	it("places the signature above the body when placement is 'top'", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "top", isReply: true});
		assert.equal(result,
			'<p><br/></p>\n' + '<hr class="ruler" style="border:1px dotted silver; width:100%;">' + HTML_SIG + '' + 'body');
	});

	it("omits the <hr> ruler when disableRuler is set", () =>
	{
		const result = MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "below", disableRuler: true});
		assert.equal(result, 'body' + HTML_SIG);
	});

	it("does nothing (returns body unchanged) when placement is 'none'", () =>
	{
		assert.equal(MailJmap.composeBodyWithSignature("body", "html", SIG, {placement: "none"}), "body");
	});

	it("does nothing when the identity has no HTML signature, even if placement isn't 'none'", () =>
	{
		assert.equal(MailJmap.composeBodyWithSignature("body", "html", NO_SIG, {placement: "below"}), "body");
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
});
