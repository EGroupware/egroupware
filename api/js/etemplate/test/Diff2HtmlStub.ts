/**
 * diff2html's real ESM build imports the bare `@profoundlogic/hogan` package, whose only
 * published entry point (`lib/hogan.js`) is Node-only (`require('./compiler')`, no browser/module
 * field, no `dist/` build despite its own comment claiming one exists) - `require` doesn't exist
 * in web-test-runner's browser test context, so any test whose import chain reaches real diff2html
 * fails to import at all ("ReferenceError: require is not defined"). Production's rollup build
 * works because `@rollup/plugin-commonjs` shims CJS interop; web-test-runner's esbuild dev-server
 * plugin does not. Et2Diff.ts only ever calls `Diff2Html.html()`, and only from inside its render()
 * method (never at module scope), so a stand-in is enough - same approach as this directory's
 * existing Diff2HtmlTypesStub.ts.
 *
 * This is NOT diff2html's markup: the real thing is a table with a file header, line numbers and
 * per-line change classes. It is one block element per changed line and nothing else, which is
 * only as much as it needs to be for a test to measure how tall a rendered diff comes out - what
 * Et2Diff's height cap and its pop-out button turn on. A test that cares what the markup actually
 * looks like cannot be written against this.
 *
 * A value with no changed lines renders nothing, matching the no-op this replaced: Et2Diff always
 * prepends a `--- diff / +++ diff` header, even to an empty value, so "empty" has to keep meaning
 * "displays nothing".
 */
export function html(diffInput : string, _configuration? : unknown) : string
{
	return String(diffInput ?? "")
		.split("\n")
		// The file header diff2html is given, and the hunk headers - neither is a changed line
		.filter(line => /^[-+]/.test(line) && !/^(---|\+\+\+)/.test(line))
		.map(line => '<div class="d2h-code-line">' + line.replace(/[<&]/g, "") + '</div>')
		.join("");
}
