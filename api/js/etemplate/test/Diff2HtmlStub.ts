/**
 * diff2html's real ESM build imports the bare `@profoundlogic/hogan` package, whose only
 * published entry point (`lib/hogan.js`) is Node-only (`require('./compiler')`, no browser/module
 * field, no `dist/` build despite its own comment claiming one exists) - `require` doesn't exist
 * in web-test-runner's browser test context, so any test whose import chain reaches real diff2html
 * fails to import at all ("ReferenceError: require is not defined"). Production's rollup build
 * works because `@rollup/plugin-commonjs` shims CJS interop; web-test-runner's esbuild dev-server
 * plugin does not. Et2Diff.ts only ever calls `Diff2Html.html()`, and only from inside its render()
 * method (never at module scope), so a no-op stub is enough for any test that merely imports the
 * chain without actually exercising diff rendering - same approach as this directory's existing
 * Diff2HtmlTypesStub.ts.
 */
export function html(_diffInput : string, _configuration? : unknown) : string
{
	return '';
}
