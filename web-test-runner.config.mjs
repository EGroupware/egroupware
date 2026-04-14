/**
 * This is the configuration file for automatic TypeScript testing
 *
 * It uses "web-test-runner" to run the tests, which are written using
 * Mocha (https://mochajs.org/) &  Chai Assertion Library (https://www.chaijs.com/api/assert/)
 * Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.
 *
 * Trouble getting tests to run?  Try manually compiling TypeScript (source & tests), that seems to help.
 */

import fs from 'fs';
import {playwrightLauncher} from '@web/test-runner-playwright';
import {esbuildPlugin} from '@web/dev-server-esbuild';

// Add any test files in app/js/test/
const appJS = fs.readdirSync('.')
.filter(
=> fs.existsSync(`${dir}/js`) && fs.existsSync(`${dir}/js/test`) && fs.statSync(`${dir}/js/test`).isDirectory(),
)


export default {
nodeResolve: true,
exclude: ['**/node_modules/**'],
filterBrowserLogs(log)
{
Silence some warnings we don't care about
st text = log && typeof log.args[0] === 'string' ? log.args[0] : '';
(text.includes('Lit is in dev mode.') || text.includes('Multiple versions of Lit loaded.'))
 false;
 true;
},
coverageConfig: {
true,
'coverage',
{
ts: 90,
ches: 65,
ctions: 80,
es: 90,
{
fig: {
'3000',
[
wrightLauncher({product: 'firefox', concurrency: 1}),
wrightLauncher({product: 'chromium'}),
Dependant on specific versions of shared libraries (libicuuc.so.66, latest is .67)
wrightLauncher({ product: 'webkit' }),
],
groups:
ame: 'api',
'api/js/etemplate/**/test/*.test.ts'
cat(
=>
 {
ame: app,
`${app}/js/**/*.test.ts`
s: [
ame: "mock-modules",
{
map dompurify requests to package ESM build so browser ESM gets default export
(source === 'dompurify' || source.startsWith('dompurify/')) {
 '/node_modules/dompurify/dist/purify.es.mjs';
(source.includes('shortcut-buttons-flatpickr')) {
 './test/FlatpickrShortcutPluginStub.js';
else if (source.includes('scrollPlugin')) {
 './test/FlatpickrScrollPluginStub.js';
st mockModule = {
"./test/ResumableStub.js",
pes": "../../node_modules/diff2html/lib/types.js",
 mockModule[source];
Handles typescript
({ts: true})
],
};
