//
// This script runs the Custom Elements Manifest analyzer to generate custom-elements.json
//

import {execSync} from 'child_process';
import commandLineArgs from 'command-line-args';

const {outdir} = commandLineArgs([
	{name: 'outdir', type: String},
	{name: 'watch', type: Boolean}
]);

execSync(`cem analyze --config "doc/etemplate2/custom-elements-manifest.config.mjs"  --outdir "${outdir}"`, {stdio: 'inherit'});
//execSync(`cem analyze --globs "api/js/etemplate/Et2Widget"  --outdir "${outdir}"`, {stdio: 'inherit'});
