/**
 * Build the minified CSS theme bundles
 *
 * Replaces grunt-contrib-cssmin (dropped together with grunt, as both grunt and
 * grunt-contrib-cssmin vendor outdated glob/minimatch/js-yaml with no upstream fix,
 * see GHSA-mh99-v99m-4gvg and related advisories).
 *
 * The file lists are NOT duplicated here: they are read straight out of
 * Gruntfile.js's cssmin config via a minimal grunt.initConfig() shim, so that file
 * stays the single source of truth for these file lists.
 *
 * Usage: node build-css.mjs (or: npm run css)
 */
import {createRequire} from 'module';
import path from 'path';
import fs from 'fs/promises';
import CleanCSS from 'clean-css';

const require = createRequire(import.meta.url);

let config = null;
require('./Gruntfile.js')({
	initConfig: cfg => config = cfg,
	loadNpmTasks: () => {},
	registerTask: () => {}
});

const {options, ...targets} = config.cssmin;

for (const [target, {files}] of Object.entries(targets))
{
	for (const [dest, src] of Object.entries(files))
	{
		const result = new CleanCSS({
			rebase: options.rebase,
			rebaseTo: path.dirname(dest),
			sourceMap: options.sourceMap,
			level: {
				1: {},
				// shorthandCompacting was a grunt-contrib-cssmin (clean-css v4) option name;
				// clean-css v5 moved it under level 2's mergeIntoShorthands
				2: {mergeIntoShorthands: options.shorthandCompacting !== false}
			}
		}).minify(src);

		if (result.errors.length)
		{
			console.error(`cssmin ${target} -> ${dest} failed:`, result.errors);
			process.exitCode = 1;
			continue;
		}
		if (result.warnings.length)
		{
			console.warn(`cssmin ${target} -> ${dest}:`, result.warnings);
		}

		let css = result.styles;
		if (options.sourceMap)
		{
			css += `\n/*# sourceMappingURL=${path.basename(dest)}.map */`;
			await fs.writeFile(dest + '.map', result.sourceMap.toString());
		}
		await fs.writeFile(dest, css);
		console.log(`Built ${dest} (${(css.length / 1024).toFixed(1)} KiB)`);
	}
}
