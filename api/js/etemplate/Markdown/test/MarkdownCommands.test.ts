/**
 * Test file for the eTemplate markdown editing commands
 *
 * Contract under test: applyCommand(value, start, end, command) returns the new markdown source
 * and where the selection belongs, without touching the DOM.  Every inline command must both
 * wrap and unwrap, so the popup's buttons toggle rather than nest, and every block command must
 * replace whatever prefix a line already has rather than stack onto it.
 *
 * Setup: the function is pure, so there is no fixture, no widget and no egw() stub.  Each case
 * states the source, the selection offsets, and the expected source plus offsets.
 *
 * Pass criteria: the returned value matches exactly, and the returned selection covers the text
 * a user would expect to keep typing over.  A failure here is a logic bug in the command, not an
 * environment problem - nothing external is involved.
 */
import {assert} from '@open-wc/testing';
import {applyCommand, minimalEdit, offsetOfLine, sourceOffsetForRendered} from "../MarkdownCommands";

/**
 * Run a command over the selection marked by | ... | in `marked`, and return the result.
 * Keeps the cases readable: "a |bold| word" instead of hand-counted offsets.
 */
function run(marked : string, command : any)
{
	const start = marked.indexOf("|");
	const end = marked.indexOf("|", start + 1) - 1;
	const value = marked.replace(/\|/g, "");
	return applyCommand(value, start, end, command);
}

describe("applyCommand() - inline commands", () =>
{
	it("wraps the selection", () =>
	{
		assert.equal(run("a |bold| word", "bold").value, "a **bold** word");
		assert.equal(run("a |em| word", "italic").value, "a *em* word");
		assert.equal(run("a |gone| word", "strikethrough").value, "a ~~gone~~ word");
		assert.equal(run("a |fn| word", "code").value, "a `fn` word");
	});

	it("leaves the selection on the wrapped text, not the delimiters", () =>
	{
		const result = run("a |bold| word", "bold");
		assert.equal(result.value.slice(result.start, result.end), "bold");
	});

	it("unwraps when the delimiters sit just outside the selection", () =>
	{
		const result = run("a **|bold|** word", "bold");
		assert.equal(result.value, "a bold word");
		assert.equal(result.value.slice(result.start, result.end), "bold");
	});

	it("unwraps when the delimiters are inside the selection", () =>
	{
		const result = run("a |**bold**| word", "bold");
		assert.equal(result.value, "a bold word");
		assert.equal(result.value.slice(result.start, result.end), "bold");
	});

	it("does not let italic steal a bold marker", () =>
	{
		// the char next to the selection is "*", but it belongs to "**"
		assert.equal(run("a **|bold|** word", "italic").value, "a ***bold*** word");
	});

	it("wraps an empty selection so the user can type inside", () =>
	{
		const result = applyCommand("ab", 1, 1, "bold");
		assert.equal(result.value, "a****b");
		assert.equal(result.start, 3);
		assert.equal(result.end, 3);
	});
});

describe("applyCommand() - link", () =>
{
	it("builds a link and selects the url placeholder", () =>
	{
		const result = run("see |here| now", "link");
		assert.equal(result.value, "see [here](url) now");
		assert.equal(result.value.slice(result.start, result.end), "url");
	});
});

describe("applyCommand() - block commands", () =>
{
	it("prefixes the line the caret is on", () =>
	{
		assert.equal(applyCommand("Title", 2, 2, "h1").value, "# Title");
		assert.equal(applyCommand("Title", 2, 2, "h3").value, "### Title");
		assert.equal(applyCommand("Title", 2, 2, "quote").value, "> Title");
		assert.equal(applyCommand("Title", 2, 2, "ul").value, "- Title");
		assert.equal(applyCommand("Title", 2, 2, "checklist").value, "- [ ] Title");
	});

	it("numbers an ordered list across the selection", () =>
	{
		const result = applyCommand("one\ntwo\nthree", 0, 13, "ol");
		assert.equal(result.value, "1. one\n2. two\n3. three");
	});

	it("prefixes every selected line", () =>
	{
		assert.equal(applyCommand("one\ntwo", 0, 7, "ul").value, "- one\n- two");
	});

	it("replaces an existing prefix rather than stacking", () =>
	{
		assert.equal(applyCommand("# Title", 3, 3, "h2").value, "## Title");
		assert.equal(applyCommand("- item", 3, 3, "ol").value, "1. item");
		assert.equal(applyCommand("> quote", 3, 3, "ul").value, "- quote");
		assert.equal(applyCommand("- [ ] task", 8, 8, "ul").value, "- task");
	});

	it("toggles off when every line already has the prefix", () =>
	{
		assert.equal(applyCommand("- one\n- two", 0, 11, "ul").value, "one\ntwo");
		assert.equal(applyCommand("## Title", 4, 4, "h2").value, "Title");
	});

	it("does not toggle off when only some lines have the prefix", () =>
	{
		assert.equal(applyCommand("- one\ntwo", 0, 9, "ul").value, "- one\n- two");
	});

	it("normal strips a prefix and never adds one", () =>
	{
		assert.equal(applyCommand("### Title", 5, 5, "normal").value, "Title");
		assert.equal(applyCommand("> quote", 3, 3, "normal").value, "quote");
		assert.equal(applyCommand("plain", 2, 2, "normal").value, "plain");
	});

	it("keeps indentation", () =>
	{
		assert.equal(applyCommand("\tone", 2, 2, "ul").value, "\t- one");
	});

	it("does not drag in the line after a selection ending on a newline", () =>
	{
		assert.equal(applyCommand("one\ntwo", 0, 4, "ul").value, "- one\ntwo");
	});
});

describe("applyCommand() - hostile offsets", () =>
{
	it("survives empty, null and out-of-range input", () =>
	{
		assert.equal(applyCommand("", 0, 0, "bold").value, "****");
		assert.equal(applyCommand(null, 0, 0, "bold").value, "****");
		assert.equal(applyCommand("ab", 99, 99, "bold").value, "ab****");
		assert.equal(applyCommand("ab", 2, 0, "bold").value, "ab****");
		assert.equal(applyCommand("ab", -5, 99, "italic").value, "*ab*");
	});
});

describe("minimalEdit()", () =>
{
	it("narrows an edit to the region that changed", () =>
	{
		// "a bold word" -> "a **bold** word" only touches the middle
		const edit = minimalEdit("a bold word", "a **bold** word");
		assert.equal(edit.from, 2);
		assert.equal(edit.to, 6);
		assert.equal(edit.text, "**bold**");
	});

	it("reports an empty edit for identical strings", () =>
	{
		const edit = minimalEdit("same", "same");
		assert.equal(edit.from, edit.to);
		assert.equal(edit.text, "");
	});

	it("handles pure insertion and pure deletion", () =>
	{
		const insert = minimalEdit("ac", "abc");
		assert.equal(insert.text, "b");
		assert.equal(insert.from, 1);
		assert.equal(insert.to, 1);

		const remove = minimalEdit("abc", "ac");
		assert.equal(remove.text, "");
		assert.equal(remove.from, 1);
		assert.equal(remove.to, 2);
	});

	it("round-trips: applying the edit reproduces the new value", () =>
	{
		const cases = [["", "x"], ["abc", ""], ["one\ntwo", "- one\n- two"], ["# T", "## T"]];
		cases.forEach(([from, to]) =>
		{
			const edit = minimalEdit(from, to);
			assert.equal(from.slice(0, edit.from) + edit.text + from.slice(edit.to), to);
		});
	});
});

describe("offsetOfLine()", () =>
{
	const SRC = "# Heading\n\na **bold** word\n\n- item";

	it("finds the start of each line", () =>
	{
		assert.equal(offsetOfLine(SRC, 0), 0);
		assert.equal(offsetOfLine(SRC, 2), SRC.indexOf("a **bold**"));
		assert.equal(offsetOfLine(SRC, 4), SRC.indexOf("- item"));
	});

	it("clamps past the end and before the start", () =>
	{
		assert.equal(offsetOfLine(SRC, 99), SRC.length);
		assert.equal(offsetOfLine(SRC, -1), 0);
		assert.equal(offsetOfLine("", 3), 0);
	});
});

describe("sourceOffsetForRendered()", () =>
{
	it("maps straight through for plain text", () =>
	{
		const src = "hello world";
		assert.equal(sourceOffsetForRendered(src, 0, "hello world", 6), 6);
	});

	it("skips markers that never reached the rendered text", () =>
	{
		//  source: "a **bold** word"   rendered: "a bold word"
		const src = "a **bold** word";
		// clicking just before "word" in the rendered text (offset 7)
		assert.equal(sourceOffsetForRendered(src, 0, "a bold word", 7), src.indexOf("word"));
		// clicking at the start of "bold"
		assert.equal(sourceOffsetForRendered(src, 0, "a bold word", 2), src.indexOf("bold"));
	});

	it("skips a heading marker", () =>
	{
		const src = "# Heading";
		assert.equal(sourceOffsetForRendered(src, 0, "Heading", 0), src.indexOf("Heading"));
	});

	it("handles the breaks:true case - one block spanning many source lines", () =>
	{
		// this is the case that made every click land on offset 0
		const src = "line one\nline two\nline three";
		const rendered = "line one\nline two\nline three";
		assert.equal(sourceOffsetForRendered(src, 0, rendered, 9), 9, "start of line two");
		assert.equal(sourceOffsetForRendered(src, 0, rendered, 23), 23, "into line three");
	});

	it("clamps instead of running away", () =>
	{
		assert.equal(sourceOffsetForRendered("abc", 0, "abc", 99), 3);
		assert.equal(sourceOffsetForRendered("abc", 0, "abc", -5), 0);
		assert.equal(sourceOffsetForRendered("", 0, "", 3), 0);
		assert.equal(sourceOffsetForRendered("abc", 99, "abc", 1), 3);
	});
});
