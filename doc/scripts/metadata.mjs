//
// This script runs the Custom Elements Manifest analyzer to generate custom-elements.json
//

import {execSync} from 'child_process';
import commandLineArgs from 'command-line-args';
import path from 'path';
import {fileURLToPath} from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const {outdir} = commandLineArgs([
	{name: 'outdir', type: String},
	{name: 'watch', type: Boolean}
]);

// Resolve the analyzer config from this script's own location so it works regardless of cwd
// (the build normally runs it from the repo root, but eleventy.config.cjs now invokes it from doc/etemplate2).
const cemConfig = path.resolve(__dirname, '../etemplate2/custom-elements-manifest.config.mjs');

execSync(`cem analyze --config "${cemConfig}" --outdir "${outdir}"`, {stdio: 'inherit'});
//execSync(`cem analyze --globs "api/js/etemplate/Et2Widget"  --outdir "${outdir}"`, {stdio: 'inherit'});
