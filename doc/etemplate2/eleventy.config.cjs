/* eslint-disable no-invalid-this */
const fs = require('fs');
const path = require('path');
const {execSync} = require('child_process');
const lunr = require('lunr');
const {capitalCase} = require('change-case');
const {customElementsManifest, getAllComponents, getAllMixins, getAllControllers, getShoelaceVersion} = require('./_utilities/cem.cjs');
const {buildTaxonomy, FEATURE_AREAS} = require('./_utilities/widget-taxonomy.cjs');
const egwFlavoredMarkdown = require('./_utilities/markdown.cjs');
const activeLinks = require('./_utilities/active-links.cjs');
const anchorHeadings = require('./_utilities/anchor-headings.cjs');
const codePreviews = require('./_utilities/code-previews.cjs');
const copyCodeButtons = require('./_utilities/copy-code-buttons.cjs');
const externalLinks = require('./_utilities/external-links.cjs');
const highlightCodeBlocks = require('./_utilities/highlight-code.cjs');
const tableOfContents = require('./_utilities/table-of-contents.cjs');
const prettier = require('./_utilities/prettier.cjs');
const scrollingTables = require('./_utilities/scrolling-tables.cjs');
const typography = require('./_utilities/typography.cjs');
const replacer = require('./_utilities/replacer.cjs');

const assetsDir = 'assets';
const cdndir = 'cdn';
const npmdir = 'dist';

// Every documented mixin/controller lists which widgets consume it (reverse of "this component's
// mixins", already on each component) so a reader landing on Et2InputWidget's page can jump to
// every input widget, and vice versa - see doc/ai/projects/etemplate-docs-sidebar-grouping.md.
function attachConsumedBy(components, mixins)
{
	mixins.forEach(mixin =>
	{
		// A controller is not in anyone's `mixins` array - it is instantiated, not mixed in - so
		// the same "who uses this?" question has to be answered from the source. Without this a
		// controller page can only say what the class does, never where it is actually used.
		if (mixin.isController)
		{
			mixin.consumedBy = components.filter(c =>
			{
				const src = path.join('..', '..', c.path.replace(/\.js$/, '.ts'));
				try
				{
					return new RegExp('new\\s+' + mixin.name + '\\s*\\(').test(fs.readFileSync(src, 'utf8'));
				}
				catch (e)
				{
					return false;
				}
			}).map(c => ({name: c.name, tagName: c.tagName}));
			return;
		}
		mixin.consumedBy = components
			.filter(c => (c.mixins || []).some(m => m.name === mixin.name))
			.map(c => ({name: c.name, tagName: c.tagName}));
	});
}

// Walks the computed taxonomy and copies belongsTo/related back onto the matching component
// object (by name), so component.njk can render a "Related" section at the end of the page
// without needing to know anything about the taxonomy tree shape itself - just its own data.
function attachTaxonomyMetadata(components, taxonomy)
{
	const byName = new Map(components.map(c => [c.name, c]));
	function visit(node)
	{
		const component = byName.get(node.name);
		if (component)
		{
			component.belongsTo = node.belongsTo;
			component.related = node.related;
		}
		(node.associated || []).forEach(visit);
	}
	taxonomy.categories.forEach(cat => cat.entries.forEach(entry =>
	{
		visit(entry.base);
		entry.variations.forEach(visit);
	}));
}

let allComponents = getAllComponents();
let allMixins = getAllMixins().concat(getAllControllers()).sort((a, b) => a.name.localeCompare(b.name));
attachConsumedBy(allComponents, allMixins);
let widgetTaxonomy = buildTaxonomy(allComponents, allMixins);
attachTaxonomyMetadata(allComponents, widgetTaxonomy);

// Reports what is still undocumented, so a gap is visible at build time instead of only to a
// reader who lands on a bare page. Summary on every build; DOCS_GAPS=1 lists the names.
// Warn-only by design - this must never fail a build, it is a to-do list, not a gate.
function reportDocumentationGaps(components, mixins)
{
	// What counts as "documented" is deliberately generous, because the alternative kept flagging
	// finished work. A widget only instantiated by another widget (et2-nextmatch-columnselection)
	// is properly documented by prose with no example at all; one that cannot work without a
	// server is documented with a static code block on purpose. Both are done, not gaps.
	//
	// So: `undocumented` is the real to-do list - nothing written for it anywhere. The live/static
	// split is reported alongside as a measure of how much of it a reader can actually try.
	const hasLiveExample = c => !!(c.content && c.content.includes(':preview'));
	const hasStaticExample = c => !!(c.content && /```/.test(c.content)) && !hasLiveExample(c);
	const isUndocumented = c => !c.content && !c.summary && !c.description;
	const undocumented = components.filter(isUndocumented);
	const liveExamples = components.filter(hasLiveExample);
	const staticExamples = components.filter(hasStaticExample);
	// Everything documented that a reader still cannot try. This has to key off "has no example"
	// rather than "has a .md", or a widget carrying only a class docblock lands in no list at all
	// and the totals stop adding up: seven widgets sat in that blind spot, counted as documented
	// by the headline and named nowhere underneath it.
	const proseOnly = components.filter(c => !isUndocumented(c) && !hasLiveExample(c) && !hasStaticExample(c));
	// A companion .md counts as documentation here exactly as it does for a widget - Et2Widget and
	// Et2InputWidget are documented that way rather than in a class docblock.
	const mixinsNoText = mixins.filter(m => !m.summary && !m.description && !m.content);
	const undescribedEvents = [];
	components.forEach(c => (c.events || []).forEach(e =>
	{
		if (!e.description) undescribedEvents.push(c.tagName + ' ' + e.name);
	}));

	// A markdown file is only ever read as `<ClassName>.md` beside that class's source. Getting
	// the name wrong is silent: the file exists, reads like working documentation, and nothing
	// renders it. Real example - Headers/CustomfieldsHeader.md was named after its *source file*
	// rather than its class (Et2CustomfieldsHeader), so a full page of prose and examples went
	// nowhere until it was renamed.
	//
	// So only look inside directories that actually define a documented class, and only complain
	// about files that are not plainly something else (a README, a plan, working notes). Matching
	// on an "Et2" prefix instead would have missed the very case above.
	const NOT_WIDGET_DOC = /^(README|CHANGELOG)|_(PLAN|NOTES|STATUS)\.md$|(Notes|Plan|Status)\.md$|-(plan|status|notes)\.md$/i;
	const classesByDir = new Map();
	components.concat(mixins).forEach(c =>
	{
		const dir = path.dirname(path.join('..', '..', c.path));
		if (!classesByDir.has(dir)) classesByDir.set(dir, new Set());
		classesByDir.get(dir).add(c.name);
	});
	const orphanDocs = [];
	classesByDir.forEach((names, dir) =>
	{
		let entries = [];
		try { entries = fs.readdirSync(dir); }
		catch (e) { return; }
		entries.filter(f => f.endsWith('.md') && !NOT_WIDGET_DOC.test(f)).forEach(f =>
		{
			if (!names.has(f.slice(0, -3))) orphanDocs.push(path.join(dir, f));
		});
	});

	// Lint the examples themselves for the mistakes that are invisible until someone opens the
	// page. Each of these has actually shipped on this site: a `debugger` that halted the
	// reader's browser, a `//` comment between tags rendering as body text, and a script reaching
	// for `.updateComplete` before the widget had upgraded (which throws, leaving the example
	// half-working with nothing to show for it).
	const exampleProblems = [];
	components.forEach(c =>
	{
		if (!c.content) return;
		const previews = c.content.match(/```html:preview[\s\S]*?```/g) || [];
		previews.forEach(block =>
		{
			const scripts = (block.match(/<script[\s\S]*?<\/script>/g) || []).join('\n');
			const markup = block.replace(/<script[\s\S]*?<\/script>/g, '');
			const flag = msg => exampleProblems.push(c.tagName + ': ' + msg);

			if (/\bdebugger\b/.test(block)) flag('`debugger` in an example');
			if (/^\s*\/\//m.test(markup)) flag('`//` comment in HTML - renders as visible text');
			if (/\bTODO\b/.test(markup)) flag('TODO left in an example');
			// Only a bare tag selector is the hazard: with several previews on a page it silently
			// picks the first widget, which belongs to a different example. A unique class or id
			// selector is fine and most existing examples already use one.
			const tagSelector = /document\.querySelector\(\s*["'`]([a-zA-Z][\w-]*)["'`]\s*\)/g;
			let m;
			while ((m = tagSelector.exec(scripts)) !== null)
			{
				flag(`document.querySelector("${m[1]}") picks the first match on the page - use an id`);
			}
			if (/\.updateComplete/.test(scripts) && !/whenDefined/.test(scripts))
			{
				flag('.updateComplete without customElements.whenDefined() - throws before upgrade');
			}
		});
	});

	const total = components.length;
	console.log(
		`[docs] ${liveExamples.length + staticExamples.length}/${total} widgets have an example ` +
		`(${liveExamples.length} live, ${staticExamples.length} static), ` +
		`${total - undocumented.length}/${total} documented at all; ` +
		`${undescribedEvents.length} undescribed events, ` +
		// Same polarity as every other figure on this line - a count of what is *done*. It read
		// "0/17 mixins undocumented" before, which is good news phrased as bad and was misread.
		`${mixins.length - mixinsNoText.length}/${mixins.length} mixins and controllers documented` +
		(orphanDocs.length ? `, ${orphanDocs.length} orphaned .md` : '') +
		(exampleProblems.length ? `, ${exampleProblems.length} example problems` : '') +
		(process.env.DOCS_GAPS ? '' : '  (DOCS_GAPS=1 for names)')
	);

	if (!process.env.DOCS_GAPS)
	{
		return;
	}
	const list = (label, names) =>
	{
		if (names.length) console.log(`[docs] ${label} (${names.length}):\n       ` + names.join('\n       '));
	};
	list('nothing written at all', undocumented.map(c => c.tagName));
	list('documented, static example only', staticExamples.map(c => c.tagName));
	list('documented, no example', proseOnly.map(c => c.tagName));
	list('undocumented mixins and controllers', mixinsNoText.map(m => m.name));
	list('events with no description', undescribedEvents);
	list('orphaned .md files (named after no documented class)', orphanDocs);
	list('problems in examples', exampleProblems);
}

reportDocumentationGaps(allComponents, allMixins);
// Every entry entryUrl() has handed to a page this build, so eleventy.after can confirm the files
// were actually copied. See the check itself for why they might not have been.
// One read of the build manifest per config evaluation. Everything that needs to agree about which
// hashed entry a page uses - the passthrough copy and entryUrl() - reads this, not the file, so a
// rollup writing new hashes mid-build cannot make them disagree.
let manifestSnapshot = {};
try { manifestSnapshot = JSON.parse(fs.readFileSync('../../api/js/build-manifest.json', 'utf8')); }
catch (e) { /* reported properly by entryUrl() when a page actually asks for an entry */ }

const resolvedEntries = new Map();
let hasBuiltSearchIndex = false;

// Write component data to file, 11ty will pick it up and create pages - the name & location are important
if (!fs.existsSync("_data"))
{
	fs.mkdirSync("_data");
}
// Only write when the content actually differs. This config is re-evaluated on every watch
// cycle, and _data/ is a data directory: rewriting these three files makes eleventy consider
// every page's data stale, so --incremental rebuilt all 155 pages (40-60s) for a one-line edit
// to a single widget's markdown. Skipping an identical write lets a .md edit rebuild only the
// page it belongs to. A real change still writes, and still correctly rebuilds everything.
function writeIfChanged(path, contents)
{
	try
	{
		if (fs.readFileSync(path, 'utf8') === contents)
		{
			return;
		}
	}
	catch (e) { /* missing or unreadable - fall through and write it */ }
	fs.writeFileSync(path, contents);
}

writeIfChanged("_data/components.json", JSON.stringify(allComponents));
writeIfChanged("_data/mixins.json", JSON.stringify(allMixins));
writeIfChanged("_data/widgetTaxonomy.json", JSON.stringify(widgetTaxonomy));

// Put it here too, since addPassthroughCopy() ignores it
writeIfChanged("assets/custom-elements.json", fs.readFileSync("../dist/custom-elements.json", 'utf8'));

module.exports = async function (eleventyConfig)
{
	// package.json overrides jsdom's html-encoding-sniffer dependency to avoid html-encoding-sniffer@6 requiring
	// ESM-only @exodus/bytes from CommonJS under Node 24.
	const {JSDOM} = await import('jsdom');

	//
	// Global data
	//
	eleventyConfig.addGlobalData('baseUrl', 'https://egroupware.org/'); // the production URL
	eleventyConfig.addGlobalData('layout', 'default'); // make 'default' the default layout
	eleventyConfig.addGlobalData('toc', true); // enable the table of contents
	eleventyConfig.addGlobalData('meta', {
		title: 'EGroupware',
		description: '',
		image: 'images/logo.svg',
		version: customElementsManifest.package.version,
		components: allComponents,
		mixins: allMixins,
		widgetTaxonomy: widgetTaxonomy,
		shoelaceVersion: getShoelaceVersion(),
		cdndir,
		npmdir
	});

	//
	// Layout aliases
	//
	eleventyConfig.addLayoutAlias('default', 'default.njk');

	//
	// Copy EGw stuff in
	//
	// General assets
	eleventyConfig.addPassthroughCopy({"../../api/templates/default/images/logo.svg": "assets/images/logo.svg"});
	eleventyConfig.addPassthroughCopy({"../../api/templates/default/etemplate2.css": "assets/styles/etemplate2.css"});
	eleventyConfig.addPassthroughCopy({"../../kdots/css/kdots.css": "assets/styles/kdots.css"});
	// The date widgets' calendar popup is flatpickr's, and flatpickr's own stylesheet is what makes
	// it visible and lays the day grid out.  The app gets it from the kdots theme, which inlines
	// this same file (see kdots/css/themes/dark.less) - but the kdots stylesheet is deliberately
	// not linked here because it breaks page scrolling, so serve flatpickr's directly.  Without it
	// etemplate2.css's `body .flatpickr-calendar {display: none}` has nothing to override it, and
	// every date widget's calendar is invisible on its own documentation page.
	eleventyConfig.addPassthroughCopy({"../../node_modules/flatpickr/dist/themes/light.css": "assets/styles/flatpickr.css"});

	// vendor requirements
	eleventyConfig.addPassthroughCopy({
		"../../vendor/bower-asset/jquery/dist/jquery.min.js": "assets/scripts/vendor/bower-asset/jquery/dist/jquery.min.js",
		"../../vendor/bower-asset/cropper/dist/cropper.min.js": "assets/scripts/vendor/bower-asset/cropper/dist/cropper.min.js",
		"../../vendor/bower-asset/diff2html/dist/diff2html.min.js": "assets/scripts/vendor/bower-asset/diff2html/dist/diff2html.min.js",
	})

	// Etemplate2
	eleventyConfig.addPassthroughCopy({"../../chunks": "assets/scripts/chunks"});
	// ...and, explicitly, the three entry chunks the pages actually name.
	//
	// Copying chunks/ wholesale is not enough on its own. A rollup running against this checkout
	// can write new hashed entries *after* that directory has been walked, so the copy carries the
	// old files while entryUrl() below resolves the manifest to the new ones - the page then names
	// chunks that were never copied, every script 404s, and not one widget registers. The page
	// still renders its prose, so it reads as "the examples are empty" rather than as a failure.
	//
	// Both sides now read one snapshot taken here (manifestSnapshot), so within a build the files
	// copied and the files named cannot disagree. The eleventy.after check still reports a miss.
	Object.entries(manifestSnapshot).forEach(([logical, hashed]) =>
	{
		if (!/^\/(api\/js\/(etemplate\/etemplate2|jsapi\/egw\.min)|kdots\/js\/app\.min)\.js$/.test(logical))
		{
			return;
		}
		const src = path.join('..', '..', hashed.replace(/^\//, ''));
		if (fs.existsSync(src))
		{
			eleventyConfig.addPassthroughCopy({[src]: path.join('assets/scripts', hashed.replace(/^\//, ''))});
		}
	});
	// The three script entries this site loads (etemplate2, egw.min, kdots app.min) are NOT copied
	// from their unhashed api/js/... paths any more - see entryUrl() below for why. Rollup writes
	// each one as a content-hashed chunk into chunks/, which the line above already copies
	// wholesale, so they are served without any further passthrough of their own.
	// Static skin/content CSS for the HtmlArea (TinyMCE) widget - resolved at runtime as
	// `${egw().webserverUrl}/api/js/etemplate/Et2HtmlArea/skins/ui/...` (Et2HtmlArea.ts). The
	// dynamic tinymce.php stylesheet and the image-upload ajax endpoint need a real PHP backend
	// and stay unavailable in the docs site; this only covers what's a static file.
	eleventyConfig.addPassthroughCopy({"../../api/js/etemplate/Et2HtmlArea/skins/ui": "assets/api/js/etemplate/Et2HtmlArea/skins/ui"});
	// api/tinymce.php normally generates the editor's content-area CSS from the user's font
	// preference (see Api\Etemplate\Widget\HtmlArea::contentCss()) - there's no PHP backend here
	// to run it, so serve TinyMCE's own stock default content CSS at that URL instead (query
	// string is ignored by the static file server). Static, but far better than a 404 that
	// aborts TinyMCE's own init - see Et2HtmlArea.ts's contentCss()/skinUrl().
	eleventyConfig.addPassthroughCopy({"../../node_modules/tinymce/skins/content/default/content.css": "assets/api/tinymce.php"});
	// Et2HtmlArea.ts computes TinyMCE's base_url as `${webserverUrl}/node_modules/tinymce` - it
	// lazy-fetches plugin i18n files (eg. the "help" plugin's keynav locale) from under there at
	// runtime, and a 404 on one of those aborts the rest of TinyMCE's own init silently (no
	// toolbar/editor renders at all). Mirror the whole package so any such fetch resolves.
	eleventyConfig.addPassthroughCopy({"../../node_modules/tinymce": "assets/node_modules/tinymce"});
	eleventyConfig.addPassthroughCopy({"../../node_modules/bootstrap-icons/font/bootstrap-icons.min.css": "assets/styles/bootstrap-icons.min.css"});
	// The CSS above references fonts/bootstrap-icons.woff(2) by relative path - without also
	// copying the font files themselves, the glyphs never render (confirmed: sidebar category
	// icons showed as broken/tofu characters until this was added).
	eleventyConfig.addPassthroughCopy({"../../node_modules/bootstrap-icons/font/fonts": "assets/styles/fonts"});
	// Et2SelectCountry pulls this in at runtime with includeCSS("api/templates/default/css/flags.css"),
	// resolved against egw().webserverUrl - which is this site's asset root. It is the only thing the
	// widget's flag icons come from, so without it every country renders with a blank flag.
	eleventyConfig.addPassthroughCopy({"../../api/templates/default/css/flags.css": "assets/api/templates/default/css/flags.css"});
	eleventyConfig.addPassthroughCopy({"../../api/js/etemplate/*/doc/*": "assets/components/"});
	eleventyConfig.addPassthroughCopy({"../../node_modules/diff2html/bundles/css/diff2html.min.css": "assets/styles/diff2html.min.css"});

	//eleventyConfig.addPassthroughCopy({"../../vendor/**/*min.js": "assets/scripts/vendor/"});
	//eleventyConfig.addPassthroughCopy("../dist/etemplate2.js", "assets/scripts/etemplate2.js");

	// Shoelace is done via CDN in default.njk

	//
	// Copy assets
	//
	eleventyConfig.addPassthroughCopy(assetsDir);

	eleventyConfig.setServerPassthroughCopyBehavior('passthrough'); // emulates passthrough copy during --serve

	//
	// Functions
	//

	// Generates a URL relative to the site's root
	eleventyConfig.addNunjucksGlobal('rootUrl', (value = '', absolute = false) =>
	{
		value = path.join('/', value);
		return absolute ? new URL(value, eleventyConfig.globalData.baseUrl).toString() : value;
	});

	// Generates a URL relative to the site's asset directory
	eleventyConfig.addNunjucksGlobal('assetUrl', (value = '', absolute = false) =>
	{
		value = path.join(`/${assetsDir}`, value);
		return absolute ? new URL(value, eleventyConfig.globalData.baseUrl).toString() : value;
	});

	const buildClassesImplementingMarkdown = (interfaceName) =>
	{
		const safeInterfaceName = String(interfaceName || '').trim();
		if(!safeInterfaceName)
		{
			return '_No interface name provided._';
		}

		const sourceRoot = path.resolve('../../api/js/etemplate');
		const results = [];
		const escapedInterfaceName = safeInterfaceName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

		function walk(dir)
		{
			for(const entry of fs.readdirSync(dir, {withFileTypes: true}))
			{
				const entryPath = path.join(dir, entry.name);
				if(entry.isDirectory())
				{
					walk(entryPath);
					continue;
				}
				if(!entry.isFile() || !entry.name.endsWith('.ts') || entry.name.endsWith('.test.ts'))
				{
					continue;
				}

				const content = fs.readFileSync(entryPath, 'utf8');
				const regex = new RegExp(`export\\s+class\\s+(\\w+)[^{]*\\bimplements\\b[^{]*\\b${escapedInterfaceName}\\b`, 'gm');
				let match;
				while((match = regex.exec(content)) !== null)
				{
					results.push({
						className: match[1]
					});
				}
			}
		}

		walk(sourceRoot);
		results.sort((a, b) => a.className.localeCompare(b.className));

		if(results.length === 0)
		{
			return `_No classes implementing \`${safeInterfaceName}\` were found._`;
		}

		let output = '';
		for(const className of [...new Set(results.map(result => result.className))])
		{
			const component = allComponents.find(c => c.name === className && c.tagName);
			if(component)
			{
				output += `- [\`${className}\`](/components/${component.tagName})\n`;
			}
			else
			{
				output += `- \`${className}\`\n`;
			}
		}
		return output;
	};

	// Includes raw markdown from a repo-relative path so docs can share a single source file.
	eleventyConfig.addNunjucksGlobal('includeRepoFile', filePath =>
	{
		const absolutePath = path.resolve(filePath);
		let content = fs.readFileSync(absolutePath, 'utf8');
		content = content.replace(/\{\{\s*classesImplementing\(\s*["']([A-Za-z0-9_$]+)["']\s*\)\s*(\|\s*safe)?\s*\}\}/g, (_match, interfaceName) =>
			buildClassesImplementingMarkdown(interfaceName)
		);
		content = content.replace(/\{\{\s*CLASSES_IMPLEMENTING:([A-Za-z0-9_$]+)\s*\}\}/g, (_match, interfaceName) =>
			buildClassesImplementingMarkdown(interfaceName)
		);
		content = content.replace(/\{\{\s*LAYOUT_HOST_WIDGETS\s*\}\}/g, buildClassesImplementingMarkdown('Et2LayoutHost'));
		return content;
	});

	// Build-time list of classes implementing a specific interface.
	eleventyConfig.addNunjucksGlobal('classesImplementing', interfaceName => buildClassesImplementingMarkdown(interfaceName));
	// Backward-compatible alias.
	eleventyConfig.addNunjucksGlobal('layoutHostWidgets', () => buildClassesImplementingMarkdown('Et2LayoutHost'));

	// Resolves one of EGroupware's script entries (etemplate2, egw.min, an app's app.min) to the
	// URL it is actually served at here.
	//
	// Rollup no longer writes an entry to its own source path: every entry is emitted as a
	// content-hashed chunk under chunks/ (rollup.config.js `entryFileNames`), and the logical ->
	// hashed mapping is recorded in api/js/build-manifest.json. The unhashed api/js/**/*.js files
	// are pre-hashing leftovers - untracked, and no build rewrites them - so linking one serves a
	// module whose own `../../../chunks/<old hash>.js` imports 404. Nothing throws and no request
	// fails visibly; window.egw is simply never defined and not one <et2-*> element upgrades, so
	// every code preview on the site renders as an empty box. Resolve through the manifest instead.
	//
	// Read fresh on each call rather than require()d, so an incremental rebuild's new hashes are
	// picked up without restarting eleventy (the manifest is written via a same-directory temp
	// file + rename, so a concurrent read never sees a half-written one).
	//
	// Manifest values are repo-root-absolute ("/chunks/x.js") and chunks/ is copied wholesale to
	// assets/scripts/chunks, which is also where each entry's sibling chunk imports and its
	// `../vendor/bower-asset/...` imports resolve from - so no per-entry passthrough is needed.
	eleventyConfig.addNunjucksGlobal('entryUrl', (logicalPath, absolute = false) =>
	{
		const manifest = manifestSnapshot;
		if (!Object.keys(manifest).length)
		{
			throw new Error('api/js/build-manifest.json is missing or empty - run "npm run build" in ' +
				'the repository root first, or wait for the rollup watch this build started to finish ' +
				'its first pass.');
		}
		if (!manifest[logicalPath])
		{
			throw new Error(`No build-manifest.json entry for "${logicalPath}" - it is stale or was ` +
				`written by a build that did not include this entry. Re-run "npm run build".`);
		}
		const value = path.join(`/${assetsDir}`, 'scripts', manifest[logicalPath]);
		resolvedEntries.set(logicalPath, value);
		return absolute ? new URL(value, eleventyConfig.globalData.baseUrl).toString() : value;
	});

	// Fetches a specific component's metadata
	eleventyConfig.addNunjucksGlobal('getComponent', tagName =>
	{
		const component = allComponents.find(c => c.tagName === tagName);
		if (!component)
		{
			throw new Error(
				`Unable to find a component called "${tagName}". Make sure the file name is the same as the component's tag ` +
				`name (minus the sl- prefix).`
			);
		}
		return component;
	});

	// Resolves an "inheritedFrom" ancestor to a documented page, for linking from the collapsed
	// "Inherited ..." sections back to whatever actually documents the member in full. Checks (in
	// order): a real Shoelace ancestor (links out to shoelace.style, since we don't document
	// Shoelace's own API) - an exact widget-name match - then a mixin match, retrying with a
	// "+ Mixin" suffix since a TS mixin factory's produced class name (what inheritedFrom.name
	// reports, e.g. "Et2WidgetWithSelect") doesn't always match the mixin's own declared name
	// (e.g. "Et2WidgetWithSelectMixin"). Returns null (render as plain text) if nothing matches.
	// `module` is the ancestor's inheritedFrom.module, used only to detect the Shoelace case.
	eleventyConfig.addNunjucksGlobal('findAncestorDoc', (name, module) =>
	{
		if (module === '@shoelace-style/shoelace')
		{
			// e.g. "SlButton" -> "button", "SlFormatBytes" -> "format-bytes" - matches Shoelace's
			// own component page URLs (the tag name minus its "sl-" prefix).
			const slug = name.replace(/^Sl/, '').replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
			return {type: 'shoelace', name, url: `https://shoelace.style/components/${slug}`};
		}
		const widget = allComponents.find(c => c.name === name);
		if (widget)
		{
			return {type: 'component', tagName: widget.tagName, name: widget.name};
		}
		const mixin = allMixins.find(m => m.name === name || m.name === name + 'Mixin');
		if (mixin)
		{
			return {type: 'mixin', name: mixin.name};
		}
		return null;
	});

	// True when a "Belongs to" value is one of the 4 recognized feature-area subsystems (as
	// opposed to rule 4's actual-inheritance-parent case, e.g. "Et2Select") - lets component.njk
	// link to that subsystem's Reference overview page instead of falling through to
	// findAncestorDoc (which wouldn't find a real component/mixin for a subsystem label anyway).
	eleventyConfig.addNunjucksGlobal('isFeatureArea', value => Object.values(FEATURE_AREAS).includes(value));

	//
	// Custom markdown syntaxes
	//
	eleventyConfig.setLibrary('md', egwFlavoredMarkdown);

	//
	// Filters
	//
	eleventyConfig.addFilter('markdown', content =>
	{
		return egwFlavoredMarkdown.render(content);
	});

	eleventyConfig.addFilter('markdownInline', content =>
	{
		return egwFlavoredMarkdown.renderInline(content);
	});

	eleventyConfig.addFilter('classNameToComponentName', className =>
	{
		let name = capitalCase(className.replace(/^Et2/, ''));
		if (name === 'Qr Code')
		{
			name = 'QR Code';
		} // manual override
		return name;
	});

	eleventyConfig.addFilter('removeEt2Prefix', tagName =>
	{
		return tagName.replace(/^et2-/, '');
	});

	//
	// Transforms
	//
	eleventyConfig.addTransform('html-transform', function (content)
	{
		// Parse the template and get a Document object
		const doc = new JSDOM(content, {
			// We must set a default URL so links are parsed with a hostname. Let's use a bogus TLD so we can easily
			// identify which ones are internal and which ones are external.
			url: `https://internal/`
		}).window.document;

		// DOM transforms
		activeLinks(doc, {pathname: this.page.url});
		anchorHeadings(doc, {
			within: '#content .content__body',
			levels: ['h2', 'h3', 'h4', 'h5']
		});
		tableOfContents(doc, {
			levels: ['h2', 'h3'],
			container: '#content .content__toc > ul',
			within: '#content .content__body'
		});
		codePreviews(doc);
		externalLinks(doc, {target: '_blank'});
		highlightCodeBlocks(doc);
		scrollingTables(doc);
		copyCodeButtons(doc); // must be after codePreviews + highlightCodeBlocks
		typography(doc, '#content');
		replacer(doc, [
			{pattern: '%VERSION%', replacement: customElementsManifest.package.version},
			{pattern: '%CDNDIR%', replacement: cdndir},
			{pattern: '%NPMDIR%', replacement: npmdir}
		]);

		// Serialize the Document object to an HTML string and prepend the doctype
		content = `<!DOCTYPE html>\n${doc.documentElement.outerHTML}`;

		// String transforms
		content = prettier(content);

		return content;
	});

	//
	// Build a search index
	//
	eleventyConfig.on('eleventy.after', ({results}) =>
	{
		// We only want to build the search index on the first run so all pages get indexed.
		if (hasBuiltSearchIndex)
		{
			return;
		}

		const map = {};
		const searchIndexFilename = path.join(eleventyConfig.dir.output, assetsDir, 'search.json');
		const lunrInput = path.resolve('../../node_modules/lunr/lunr.min.js');
		const lunrOutput = path.join(eleventyConfig.dir.output, assetsDir, 'scripts/lunr.js');
		const searchIndex = lunr(function ()
		{
			// The search index uses these field names extensively, so shortening them can save some serious bytes. The
			// initial index file went from 468 KB => 401 KB by using single-character names!
			this.ref('id'); // id
			this.field('t', {boost: 50}); // title
			this.field('h', {boost: 25}); // headings
			this.field('c'); // content

			results.forEach((result, index) =>
			{
				const url = path
					.join('/', path.relative(eleventyConfig.dir.output, result.outputPath))
					.replace(/\\/g, '/') // convert backslashes to forward slashes
					.replace(/\/index.html$/, '/'); // convert trailing /index.html to /
				const doc = new JSDOM(result.content, {
					// We must set a default URL so links are parsed with a hostname. Let's use a bogus TLD so we can easily
					// identify which ones are internal and which ones are external.
					url: `https://internal/`
				}).window.document;
				const content = doc.querySelector('#content');

				// Get title and headings
				const title = (doc.querySelector('title')?.textContent || path.basename(result.outputPath)).trim();
				const headings = [...content.querySelectorAll('h1, h2, h3, h4')]
					.map(heading => heading.textContent)
					.join(' ')
					.replace(/\s+/g, ' ')
					.trim();

				// Remove code blocks and whitespace from content
				[...content.querySelectorAll('code[class|=language]')].forEach(code => code.remove());
				const textContent = content.textContent.replace(/\s+/g, ' ').trim();

				// Update the index and map
				this.add({id: index, t: title, h: headings, c: textContent});
				map[index] = {title, url};
			});
		});

		// Copy the Lunr search client and write the index
		fs.mkdirSync(path.dirname(lunrOutput), {recursive: true});
		fs.copyFileSync(lunrInput, lunrOutput);
		fs.writeFileSync(searchIndexFilename, JSON.stringify({searchIndex, map}), 'utf-8');

		hasBuiltSearchIndex = true;
	});

	//
	// Send a signal to stdout that let's the build know we've reached this point
	//
	eleventyConfig.on('eleventy.after', () =>
	{
		// chunks/ is copied wholesale by a passthrough step, but entryUrl() resolves each entry
		// against api/js/build-manifest.json at render time. A rollup running against this same
		// checkout - the app's own `rollup -cw`, not necessarily this docs build - can rewrite the
		// manifest with fresh hashes in between, so the HTML ends up naming chunks that this build
		// never copied. Nothing errors: the scripts 404, window.egw is never defined, and every
		// widget on every page silently fails to upgrade while the page still looks fine.
		// Cheap to check, and it turns a baffling empty site into one line.
		resolvedEntries.forEach((url, logicalPath) =>
		{
			const onDisk = path.join('..', 'dist', 'site', url.replace(/^\//, ''));
			if (!fs.existsSync(onDisk))
			{
				console.warn(`[docs] MISSING ENTRY: ${logicalPath} resolved to ${url}, which was not ` +
					`copied to the output. Every widget will silently fail to upgrade. A rebuild ` +
					`usually re-runs the passthrough copy and fixes it.`);
			}
		});
		resolvedEntries.clear();

		console.log('[eleventy.after]');
	});

	//
	// Keep component docs fresh under --watch.
	//
	// Component markdown (api/js/etemplate/**/*.md) and the generated API tables
	// (custom-elements.json from cem analyze) are NOT in 11ty's input/_data graph,
	// so the watcher never sees them and the top-level getAllComponents() run above
	// only happens once at config load. Watch the source tree and, before every
	// rebuild, regenerate the manifest from TypeScript and re-read component content
	// into _data/components.json so the page actually reflects the edit.
	//
	// cem analyze only needs to run when a .ts file changed — editing a component's
	// .md doc is just a content change that getAllComponents() re-reads cheaply without
	// re-analyzing TypeScript. We also write the manifest to doc/dist only (the single
	// source cem.cjs consumes); the previous second run into _data was never read.
	//
	eleventyConfig.addWatchTarget("../../api/js/etemplate/**/*.{ts,md}");
	eleventyConfig.addWatchTarget("../../api/js/build-manifest.json");

	eleventyConfig.on('eleventy.beforeWatch', (queue) =>
	{
		// Only re-run the (expensive) cem analyzer when a TypeScript source file changed.
		const tsChanged = (queue || []).some(file => file.endsWith('.ts'));
		if (tsChanged)
		{
			// metadata.mjs resolves its own paths; run from the repo root so cem analyze finds package.json.
			const repoRoot = path.resolve(__dirname, '../..');
			const metadataScript = path.resolve(repoRoot, 'doc/scripts/metadata.mjs');
			execSync(`node "${metadataScript}" --outdir "${path.resolve(__dirname, '../dist')}"`, {cwd: repoRoot, stdio: 'inherit'});
		}

		// Re-read component markdown + manifest into components.json for the page render.
		// Cheap, and also runs when only a .md doc changed so its content is refreshed.
		allComponents = getAllComponents();
		// Must match the initial computation at the top of this file exactly. It used to call
		// getAllMixins() alone, so every rebuild silently dropped the controllers that the startup
		// run had included - the pages existed after a restart and disappeared on the next edit.
		allMixins = getAllMixins().concat(getAllControllers()).sort((a, b) => a.name.localeCompare(b.name));
		attachConsumedBy(allComponents, allMixins);
		widgetTaxonomy = buildTaxonomy(allComponents, allMixins);
		attachTaxonomyMetadata(allComponents, widgetTaxonomy);
		if (!fs.existsSync("_data"))
		{
			fs.mkdirSync("_data");
		}
		// writeIfChanged, not writeFileSync: these three files are 11ty data files, so rewriting
		// them unconditionally marks every page's data stale and turns each rebuild into a full
		// 155-page one (~50s) even when a single widget's markdown changed.
		writeIfChanged("_data/components.json", JSON.stringify(allComponents));
		writeIfChanged("_data/mixins.json", JSON.stringify(allMixins));
		writeIfChanged("_data/widgetTaxonomy.json", JSON.stringify(widgetTaxonomy));
	});

	//
	// Dev server options (see https://www.11ty.dev/docs/dev-server/#options)
	//
	eleventyConfig.setServerOptions({
		domDiff: false, // disable dom diffing so custom elements don't break on reload,
		port: 4000, // if port 4000 is taken, 11ty will use the next one available
		watch: ['cdn/**/*'] // additional files to watch that will trigger server updates (array of paths or globs)
	});

	//
	// 11ty config
	//
	return {
		dir: {
			input: 'pages',
			output: '../dist/site',
			includes: '../_includes', // resolved relative to the input dir
			data: '../_data'
		},
		markdownTemplateEngine: 'njk', // use Nunjucks instead of Liquid for markdown files
		templateEngineOverride: ['njk'] // just Nunjucks and then markdown
	};
};
