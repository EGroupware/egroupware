import {deleteAsync} from 'del';
import {exec, spawn} from 'child_process';
import {globby} from 'globby';
import browserSync from 'browser-sync';
import chalk from 'chalk';
import commandLineArgs from 'command-line-args';
import copy from 'recursive-copy';
import esbuild from 'esbuild';
import fs from 'fs/promises';
import getPort, {portNumbers} from 'get-port';
import ora from 'ora';
import util from 'util';
import * as path from 'path';
import {readFileSync} from 'fs';
import {replace} from 'esbuild-plugin-replace';

const {serve, dev} = commandLineArgs([
	{name: 'serve', type: Boolean},
	{name: 'dev', type: Boolean}
]);
const outdir = 'doc/dist';
const cdndir = 'cdn';
const sitedir = 'doc/dist/site';
const spinner = ora({hideCursor: false}).start();
const execPromise = util.promisify(exec);
const eleventyReadyFile = path.join(sitedir, 'assets/search.json');
let childProcess;
let buildResults = [];

// Only doc/dist needs CEM-generated output; doc/etemplate2/_data is the 11ty data dir
// and its custom-elements.json was never consumed (cem.cjs reads doc/dist/custom-elements.json).
const bundleDirectories = [outdir];
let packageData = JSON.parse(readFileSync(path.join(process.cwd(), 'package.json'), 'utf-8'));
const egwVersion = JSON.stringify(packageData.version.toString());

// Checks for classes that look like webComponents but don't have a tagName
// We need to skip them to avoid breaking eleventy, but if it's an accident we want to know about it
async function warnMissingTagNames()
{
	const manifest = JSON.parse(await fs.readFile(path.join(outdir, 'custom-elements.json'), 'utf-8'));
	manifest.modules?.forEach(module =>
	{
		module.declarations?.forEach(declaration =>
		{
			if (declaration.customElement && !declaration.tagName)
			{
				console.warn(`Skipping custom element declaration without tagName: ${declaration.name} in ${module.path}`);
			}
		});
	});
}

// Cleanup on exit
process.on('SIGINT', handleCleanup);
process.on('SIGTERM', handleCleanup);

//
// Runs 11ty and builds the docs. The returned promise resolves after the initial publish has completed. The child
// process and an array of strings containing any output are included in the resolved promise.
//
// To debug:
// > DEBUG=Eleventy* npx @11ty/eleventy
//
async function buildTheDocs(watch = false)
{
	return new Promise(async (resolve, reject) =>
	{
		const afterSignal = '[eleventy.after]';
		const args = ['@11ty/eleventy', '--quiet'];
		const output = [];
		const errorOutput = [];
		let settled = false;

		if (watch)
		{
			args.push('--watch');
			args.push('--incremental');
		}

		// To debug use this in terminal: DEBUG=Eleventy* npx @11ty/eleventy
		const child = spawn('npx', args, {
			timeout: 120000,
			stdio: watch ? 'inherit' : 'pipe',
			cwd: 'doc/etemplate2'
		});

		if (!watch)
		{
			child.stdout.on('data', data =>
			{
				if (data.includes(afterSignal))
				{
					return;
				} // don't log the signal
				output.push(data.toString());
			});

			child.stderr.on('data', data =>
			{
				errorOutput.push(data.toString());
			});
		}

		child.on('error', reject);

		if (watch)
		{
			const started = Date.now();
			const readyInterval = setInterval(async () =>
			{
				if (settled)
				{
					return;
				}

				try
				{
					await fs.access(eleventyReadyFile);
					settled = true;
					clearInterval(readyInterval);
					resolve({child, output});
				}
				catch (e)
				{
					if (Date.now() - started > 120000)
					{
						settled = true;
						clearInterval(readyInterval);
						child.kill('SIGTERM');
						reject(new Error(`Timed out waiting for ${eleventyReadyFile}`));
					}
				}
			}, 500);

			child.on('close', (code, signal) =>
			{
				if (settled)
				{
					return;
				}
				clearInterval(readyInterval);
				const err = new Error(`Eleventy exited before the initial build completed with code ${code}, signal ${signal}`);
				err.stdout = output.join('');
				err.stderr = errorOutput.join('');
				reject(err);
			});
		}
		else
		{
			child.on('close', (code, signal) =>
			{
				if (code !== 0)
				{
					const err = new Error(`Eleventy exited with code ${code}, signal ${signal}`);
					err.stdout = output.join('');
					err.stderr = errorOutput.join('');
					reject(err);
					return;
				}
				resolve({child, output});
			});
		}
	});
}

//
// Builds the source with esbuild.
//
async function buildTheSource()
{
	const alwaysExternal = [/*'@lit',*/ 'jquery'];

	const cdnConfig = {
		format: 'esm',
		target: 'es2017',
		entryPoints: [
			//
			// NOTE: Entry points must be mapped in package.json > exports, otherwise users won't be able to import them!
			//
			// The whole shebang
			'./api/js/etemplate/etemplate2.ts',
			// The auto-loader
			//'./src/shoelace-autoloader.ts',
			// Components
			//...(await globby('./src/components/**/!(*.(style|test)).ts')),
			// Translations
			//...(await globby('./src/translations/**/*.ts')),
			// Public utilities
			//...(await globby('./src/utilities/**/!(*.(style|test)).ts')),
			// Theme stylesheets
			//...(await globby('./src/themes/**/!(*.test).ts')),
			// React wrappers
			//...(await globby('./src/react/**/*.ts'))
		],
		outdir: sitedir + '/assets/scripts',
		chunkNames: 'chunks/[name].[hash]',
		define: {
			// Floating UI requires this to be set
			'process.env.NODE_ENV': '"production"'
		},
		bundle: true,
		//
		// We don't bundle certain dependencies in the unbundled build. This ensures we ship bare module specifiers,
		// allowing end users to better optimize when using a bundler. (Only packages that ship ESM can be external.)
		//
		// We never bundle React or @lit-labs/react though!
		//
		external: alwaysExternal,
		splitting: true,
		plugins: [
			replace({
				__EGROUPWARE_VERSION__: egwVersion
			})
		]
	};

	const npmConfig = {
		...cdnConfig,
		external: undefined,
		minify: false,
		packages: 'external',
		outdir
	};

	if (serve)
	{
		// Use the context API to allow incremental dev builds
		const contexts = await Promise.all([esbuild.context(cdnConfig), esbuild.context(npmConfig)]);
		await Promise.all(contexts.map(context => context.rebuild()));
		return contexts;
	}
	else
	{
		// Use the standard API for production builds
		return await Promise.all([esbuild.build(cdnConfig), esbuild.build(npmConfig)]);
	}
}

async function rollup(watch = false)
{
	return new Promise(async (resolve, reject) =>
	{
		const afterSignal = '[rollup.after]';
		const args = ['--silent'];
		const output = [];

		if (watch)
		{
			args.push('--watch');
			args.push('--incremental');
		}

		// To debug use this in terminal: DEBUG=Eleventy* npx @11ty/eleventy
		const child = spawn('rollup', args, {
			stdio: 'pipe',
			cwd: '.',
			shell: true // for Windows
		});

		child.stdout.on('data', data =>
		{
			if (data.includes(afterSignal))
			{
				return;
			} // don't log the signal
			output.push(data.toString());
		});

		// Not even waiting
		resolve({child, output});
	});
}

//
// Called on SIGINT or SIGTERM to cleanup the build and child processes.
//
function handleCleanup()
{
	buildResults.forEach(result => result.dispose());

	if (childProcess)
	{
		childProcess.kill('SIGINT');
	}

	process.exit();
}

//
// Helper function to draw a spinner while tasks run.
//
async function nextTask(label, action)
{
	spinner.text = label;
	spinner.start();

	try
	{
		await action();
		spinner.stop();
		console.log(`${chalk.green('✔')} ${label}`);
	}
	catch (err)
	{
		spinner.stop();
		console.error(`${chalk.red('✘')} ${err}`);
		if (err.stdout)
		{
			console.error(chalk.red(err.stdout));
		}
		if (err.stderr)
		{
			console.error(chalk.red(err.stderr));
		}
		process.exit(1);
	}
}

await nextTask('Cleaning up the previous build', async () =>
{
	await Promise.all([deleteAsync(sitedir), ...bundleDirectories.map(dir => deleteAsync(dir))]);
	await fs.mkdir(outdir, {recursive: true});
});

await nextTask('Generating component metadata', () =>
{
	return Promise.all(
		bundleDirectories.map(dir =>
		{
			return execPromise(`node doc/scripts/metadata.mjs --outdir "${dir}"`, {stdio: 'inherit'});
		})
	);
});
await warnMissingTagNames();
/*
await nextTask('Generating themes', () =>
{
	return execPromise(`node scripts/make-themes.js --outdir "${outdir}"`, {stdio: 'inherit'});
});
*/
/* We don't do these
await nextTask('Running the TypeScript compiler', () =>
{
	return execPromise(`tsc --project ./tsconfig.json --outdir "${outdir}"`, {stdio: 'inherit'});
});
await nextTask('Building source files', async () =>
{
	buildResults = await buildTheSource();
});
*/

// EGroupware way of packaging
// We can't watch
await nextTask('Rolling up', async () =>
{
	await rollup(dev);
});


// Launch the dev server
if (serve)
{
	let result;

	// Spin up Eleventy and Wait for the search index to appear before proceeding. The search index is generated during
	// eleventy.after, so it appears after the docs are fully published. This is kinda hacky, but here we are.
	// Kick off the Eleventy dev server with --watch and --incremental
	await nextTask('Building docs', async () =>
	{
		result = await buildTheDocs(true);
	});

	const bs = browserSync.create();
	const port = await getPort({port: portNumbers(4000, 4999)});
	const browserSyncConfig = {
		startPath: '/',
		port,
		logLevel: 'silent',
		logPrefix: '[egw]',
		logFileChanges: true,
		notify: false,
		single: false,
		ghostMode: false,
		server: {
			baseDir: sitedir,
			routes: {
				'/dist': './cdn'
			}
		}
	};

	// Launch browser sync
	bs.init(browserSyncConfig, () =>
	{
		const url = `http://localhost:${port}`;
		console.log(chalk.cyan(`\n🥾 The dev server is available at ${url}`));

		// Log deferred output
		if (result.output.length > 0)
		{
			console.log('\n' + result.output.join('\n'));
		}

		// Log output that comes later on
		result.child.stdout?.on('data', data =>
		{
			console.log(data.toString());
		});
	});

	// Rebuilds are handled entirely by Eleventy's --watch: eleventy.config.cjs adds
	// api/js/etemplate as a watch target and re-runs cem analyze (metadata) in its
	// eleventy.beforeWatch hook (only when a .ts file changed). BrowserSync only serves
	// and reloads when the built output changes, so there is nothing to rebuild here.

	// Reload when the built docs change
	bs.watch([`${sitedir}/**/*.*`]).on('change', filename =>
	{
		bs.reload();
	});
}


// Build for production
if (!serve)
{
	let result;

	await nextTask('Building the docs', async () =>
	{
		result = await buildTheDocs();
	});

	// Log deferred output
	if (result.output.length > 0)
	{
		console.log('\n' + result.output.join('\n'));
	}
	process.exit(0);
}
