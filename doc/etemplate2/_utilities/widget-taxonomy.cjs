const fs = require('fs');
const path = require('path');

// Repo root, resolved relative to this file (doc/etemplate2/_utilities/widget-taxonomy.cjs -> repo root)
const REPO_ROOT = path.join(__dirname, '..', '..', '..');
const ETEMPLATE_SRC = path.join(REPO_ROOT, 'api', 'js', 'etemplate');

//
// Rule 2: the ~8 curated category buckets (deliberate exception to "no hardcoded list" -
// see doc/ai/projects/etemplate-docs-sidebar-grouping.md)
//
const CATEGORIES = {
	LAYOUT: 'Layout',
	INPUT: 'Input / Forms',
	DISPLAY: 'Display / Info',
	MEDIA: 'Media',
	NAVIGATION: 'Navigation & Menus',
	DIALOGS: 'Dialogs & Feedback',
	DATA_GRID: 'Data Grid',
	CONTROLLERS: 'Controllers & Mixins'
};

const CATEGORY_ICONS = {
	[CATEGORIES.LAYOUT]: 'bi-layout-wtf',
	[CATEGORIES.INPUT]: 'bi-input-cursor',
	[CATEGORIES.DISPLAY]: 'bi-card-text',
	[CATEGORIES.MEDIA]: 'bi-image',
	[CATEGORIES.NAVIGATION]: 'bi-list-check',
	[CATEGORIES.DIALOGS]: 'bi-window-stack',
	[CATEGORIES.DATA_GRID]: 'bi-table',
	[CATEGORIES.CONTROLLERS]: 'bi-puzzle'
};

// Rule 2's named exceptions for single widgets that don't fit the Input/Display split cleanly.
// Media and Navigation entries added after noticing (by dumping the real "Display / Info" bucket
// contents post-build) that those two categories from the plan's 8-bucket list were never
// actually populated - Et2Image/Et2Avatar/Et2Badge and Et2MenuItem/Et2Favorites all fell into
// Display/Info by the plain mixin-based default, since nothing routed them elsewhere.
const CATEGORY_OVERRIDES = {
	Et2Nextmatch: CATEGORIES.DATA_GRID,
	Et2Datagrid: CATEGORIES.DATA_GRID,
	Et2Dialog: CATEGORIES.DIALOGS,
	Et2Portlet: CATEGORIES.DIALOGS,
	Et2Image: CATEGORIES.MEDIA,
	Et2Avatar: CATEGORIES.MEDIA,
	Et2AvatarGroup: CATEGORIES.MEDIA,
	Et2Badge: CATEGORIES.MEDIA,
	Et2MenuItem: CATEGORIES.NAVIGATION,
	Et2FavoritesMenu: CATEGORIES.NAVIGATION
};

// Maps an explicit `@category` jsDoc tag value (see custom-elements-manifest.config.mjs) to a
// real category. Short lowercase values so they're easy to type in a docblock; CONTROLLERS is
// deliberately omitted since it's mixin-only, not something a widget class would declare itself.
const CATEGORY_TAG_VALUES = {
	layout: CATEGORIES.LAYOUT,
	input: CATEGORIES.INPUT,
	display: CATEGORIES.DISPLAY,
	media: CATEGORIES.MEDIA,
	navigation: CATEGORIES.NAVIGATION,
	dialogs: CATEGORIES.DIALOGS,
	'data-grid': CATEGORIES.DATA_GRID
};

// Rule 3's 4-entry recognized feature-area directory list
const FEATURE_AREAS = {
	Et2Vfs: 'Filemanager',
	Et2Nextmatch: 'Nextmatch',
	Et2Customfields: 'Custom fields',
	Et2Link: 'Linking system'
};

// Rule 4's association-defining mixins: mixin name -> the family root it redirects placement to
const ASSOCIATION_MIXINS = {
	FilterMixin: 'Et2NextmatchHeader'
};

//
// Path helpers
//

// component.path looks like "api/js/etemplate/Et2Select/Tag/Et2Tag.js" - keep FULL depth after
// "etemplate/", not just the first segment (that flattening bug is exactly what hid
// Et2Select/Tag/ during planning - see the plan doc's rule 5 correction).
function directoryOf(component)
{
	if (!component.path)
	{
		return null;
	}
	const match = component.path.match(/etemplate\/(.+)\/[^/]+$/);
	return match ? match[1] : null;
}

function topDirOf(component)
{
	const dir = directoryOf(component);
	return dir ? dir.split('/')[0] : null;
}

function ownSourceFile(component)
{
	if (!component.path)
	{
		return null;
	}
	return path.join(REPO_ROOT, component.path.replace(/\.js$/, '.ts'));
}

function escapeRegex(s)
{
	return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

//
// Filesystem scanning (rule 5's usage signals, and rule 5b's .xet confirmation)
//

function walkFiles(dir, exts, excludeDirNames, results)
{
	results = results || [];
	let entries;
	try
	{
		entries = fs.readdirSync(dir, {withFileTypes: true});
	}
	catch (e)
	{
		return results;
	}
	for (const entry of entries)
	{
		if (excludeDirNames.has(entry.name))
		{
			continue;
		}
		const full = path.join(dir, entry.name);
		if (entry.isDirectory())
		{
			walkFiles(full, exts, excludeDirNames, results);
		}
		else if (exts.some(ext => entry.name.endsWith(ext)))
		{
			results.push(full);
		}
	}
	return results;
}

// Scans every .ts source file once and, for each candidate tag, records which files reference it
// as a literal child tag (<tag ...>), as a Lit `literal`tag`` static-tag-name reference (needed for
// the Et2Select/Tag/ family, whose parent widgets swap tags via `get tagTag()`), or construct it via
// document.createElement(tagName).
function scanSourceUsage(tagNames)
{
	const tsFiles = walkFiles(ETEMPLATE_SRC, ['.ts'], new Set(['node_modules']))
		.filter(f => !f.endsWith('.test.ts'));

	const fileContents = tsFiles.map(f => ({file: f, content: fs.readFileSync(f, 'utf8')}));

	const usage = new Map();
	tagNames.forEach(tag =>
	{
		const literalRe = new RegExp('<' + escapeRegex(tag) + '($|[\\s>/])', 'm');
		const staticRe = new RegExp('literal`' + escapeRegex(tag) + '`');
		const createRe = new RegExp('createElement\\([\'"]' + escapeRegex(tag) + '[\'"]\\)');
		// Broader net: a bare quoted-string reference anywhere (loadWebComponent("tag", ...),
		// a `type: 'tag'` config value, ...) also counts as "this file knows about the widget",
		// even without a literal <tag> child or a Lit `literal` static-tag getter. Found by testing:
		// Et2VfsSelectButton's real tag (et2-vfs-select) is invoked via loadWebComponent() in one
		// file and a `type:` string in another, neither of which the two checks above would catch -
		// without this it looked single-parent (associated) when it's a genuinely reusable widget.
		const stringRe = new RegExp('[\'"]' + escapeRegex(tag) + '[\'"]');

		const literalFiles = new Set();
		const staticFiles = new Set();
		const createElementFiles = new Set();
		const stringFiles = new Set();

		fileContents.forEach(({file, content}) =>
		{
			if (literalRe.test(content))
			{
				literalFiles.add(file);
			}
			if (staticRe.test(content))
			{
				staticFiles.add(file);
			}
			if (createRe.test(content))
			{
				createElementFiles.add(file);
			}
			if (stringRe.test(content))
			{
				stringFiles.add(file);
			}
		});

		usage.set(tag, {literalFiles, staticFiles, createElementFiles, stringFiles});
	});

	return usage;
}

// Confirmation check for rule 5b (nested-subdirectory candidates): does the tag ever appear
// directly in a .xet template? Used ONLY to disqualify false positives like Et2CategoryBox, not
// as the primary detector (that signal was rejected - too noisy across the full component set).
function scanXetUsage(tagNames)
{
	const xetFiles = walkFiles(REPO_ROOT, ['.xet'],
		new Set(['node_modules', '.git', 'vendor', 'doc', 'var', 'tmp', 'coverage']));
	const contents = xetFiles.map(f => fs.readFileSync(f, 'utf8'));

	const hasHits = new Map();
	tagNames.forEach(tag =>
	{
		const re = new RegExp('<' + escapeRegex(tag) + '($|[\\s>/])', 'm');
		hasHits.set(tag, contents.some(c => re.test(c)));
	});
	return hasHits;
}

//
// Rule 1: global inheritance forest
//

function buildInheritanceIndex(allComponents)
{
	const byName = new Map(allComponents.map(c => [c.name, c]));

	function resolveRoot(name, seen)
	{
		seen = seen || new Set();
		if (seen.has(name))
		{
			return name;
		}
		seen.add(name);
		const c = byName.get(name);
		if (!c)
		{
			return name;
		}
		const sc = c.superclass;
		if (sc && sc.name && byName.has(sc.name) && byName.get(sc.name).tagName)
		{
			return resolveRoot(sc.name, seen);
		}
		return name;
	}

	const rootOf = new Map();
	allComponents.forEach(c => rootOf.set(c.name, resolveRoot(c.name)));

	return {byName, rootOf};
}

// Whether Et2InputWidget is anywhere in this component's mixin chain. Originally walked the
// superclass chain by hand via `byName` - but that missed cases where the mixin is applied
// *inside* another mixin's own composition rather than directly on the widget's class
// declaration (e.g. Et2Select's `.mixins` only lists `Et2WithSearchMixin`; `Et2InputWidget` is
// nested inside `Et2WidgetWithSelectMixin`'s own implementation). cem.cjs's inheritedProperties/
// inheritedMethods groups already carry CEM's own fully-resolved ancestor chain (member-level,
// not just the class-declaration-level `.mixins` array), so checking those directly is both
// simpler and correct where the hand-rolled chain walk wasn't - confirmed against real data
// (Et2Select was wrongly landing in Display/Info until this fix).
function hasInputWidgetMixin(component)
{
	if ((component.mixins || []).some(m => m.name === 'Et2InputWidget'))
	{
		return true;
	}
	const inheritedGroups = [...(component.inheritedProperties || []), ...(component.inheritedMethods || [])];
	return inheritedGroups.some(g => g.name === 'Et2InputWidget');
}

function categoryOf(component)
{
	// Explicit @category jsDoc tag (see custom-elements-manifest.config.mjs's
	// egroupware-category-tag plugin) wins over everything - it's how a widget's author overrides
	// the automatic default when it's wrong (e.g. Et2Diff carries Et2InputWidget for API
	// consistency but is a read-only diff viewer, not a real input).
	if (component.category && CATEGORY_TAG_VALUES[component.category])
	{
		return CATEGORY_TAG_VALUES[component.category];
	}
	if (CATEGORY_OVERRIDES[component.name])
	{
		return CATEGORY_OVERRIDES[component.name];
	}
	if (topDirOf(component) === 'Layout')
	{
		return CATEGORIES.LAYOUT;
	}
	return hasInputWidgetMixin(component) ? CATEGORIES.INPUT : CATEGORIES.DISPLAY;
}

function slugify(tagName)
{
	return tagName;
}

//
// Main entry point
//

function buildTaxonomy(allComponents, allMixins)
{
	allMixins = allMixins || [];
	const tagged = allComponents.filter(c => c.tagName);
	const {byName, rootOf} = buildInheritanceIndex(allComponents);

	// ---- Rule 5a: single-parent usage scan ----
	const usage = scanSourceUsage(tagged.map(c => c.tagName));
	const fileToComponent = new Map();
	allComponents.forEach(c =>
	{
		const file = ownSourceFile(c);
		if (file)
		{
			fileToComponent.set(file, c.name);
		}
	});

	// Zero-.xet-hits is required to confirm ANY associated classification, not just the
	// nested-subdirectory signal below - a single-parent literal-tag match isn't enough on its own:
	// e.g. Et2VfsSelectDialog.ts hardcodes <et2-searchbox>/<et2-vfs-path>/<et2-label>/
	// <et2-vfs-upload> as real UI pieces in its own dialog markup, but all four are also genuine,
	// independently-placed widgets (5, 5, 11, 2 real .xet hits respectively) - confirmed by testing
	// against the real repo, not assumed. Compute this once, up front, for every tagged component.
	const xetHits = scanXetUsage(tagged.map(c => c.tagName));

	const associatedParentOf = new Map();
	tagged.forEach(c =>
	{
		if (xetHits.get(c.tagName))
		{
			return; // placed directly by template authors somewhere - never associated
		}
		const ownFile = ownSourceFile(c);
		const found = usage.get(c.tagName) ||
			{literalFiles: new Set(), staticFiles: new Set(), createElementFiles: new Set(), stringFiles: new Set()};
		const otherLiteral = [...found.literalFiles].filter(f => f !== ownFile);
		const otherStatic = [...found.staticFiles].filter(f => f !== ownFile);
		const otherCreate = [...found.createElementFiles].filter(f => f !== ownFile);
		const otherString = [...found.stringFiles].filter(f => f !== ownFile);
		// literal/static pick WHO the parent is (precise); stringFiles only disqualifies (broad net -
		// if some OTHER file also references the tag any way at all, it isn't truly single-parent).
		const combined = new Set([...otherLiteral, ...otherStatic]);
		const combinedWithStrings = new Set([...combined, ...otherString]);

		if (combined.size === 1 && otherCreate.length < 2 && combinedWithStrings.size === combined.size)
		{
			const parentFile = [...combined][0];
			const parentName = fileToComponent.get(parentFile);
			if (parentName && parentName !== c.name)
			{
				associatedParentOf.set(c.name, parentName);
			}
		}
	});

	// ---- Rule 5b: nested-subdirectory candidates, confirmed via the same zero-.xet-hits check ----
	const roots = tagged.filter(c => rootOf.get(c.name) === c.name);
	const dirToRoot = new Map();
	roots.forEach(r =>
	{
		const d = directoryOf(r);
		if (d)
		{
			dirToRoot.set(d, r.name);
		}
	});

	const nestedCandidates = roots.filter(r => !associatedParentOf.has(r.name) && (directoryOf(r) || '').includes('/'));

	nestedCandidates.forEach(r =>
	{
		const dir = directoryOf(r);
		const parentDir = dir.split('/').slice(0, -1).join('/');
		const parentRootName = dirToRoot.get(parentDir);
		if (parentRootName && parentRootName !== r.name && !xetHits.get(r.tagName))
		{
			associatedParentOf.set(r.name, parentRootName);
		}
	});

	// Propagate associated status from a root to its own variations (e.g. every nextmatch header
	// variation follows Et2NextmatchHeader's associated-with-Et2Nextmatch status)
	tagged.forEach(c =>
	{
		if (associatedParentOf.has(c.name))
		{
			return;
		}
		const root = rootOf.get(c.name);
		if (root !== c.name && associatedParentOf.has(root))
		{
			associatedParentOf.set(c.name, associatedParentOf.get(root));
		}
	});

	// component name -> actual inheritance-parent name, whenever something ELSE takes over primary
	// placement and the real superclass becomes a "Belongs to" cross-link instead. Filled in here
	// AND by rule 4 below - both write into the same map, since both are the same shape of conflict
	// (a stronger, more specific placement signal beating plain inheritance).
	const belongsToOverride = new Map();

	// A component can be BOTH a genuine inheritance variation (rule 1) AND independently
	// single-parent-embedded elsewhere (rule 5) - e.g. Et2LinkPasteDialog really is a
	// Et2VfsSelectDialog subclass by inheritance, but is also the one thing Et2LinkTo embeds.
	// Found by testing against the real manifest (it silently double-listed the component under
	// both parents until this ran). Resolved the same way rule 4 resolves its own conflict: the
	// more specific single-parent signal wins the physical placement, the inheritance parent is
	// demoted to a "Belongs to" tag instead of a second nesting.
	tagged.forEach(c =>
	{
		if (!associatedParentOf.has(c.name))
		{
			return;
		}
		const trueParent = rootOf.get(c.name);
		if (trueParent !== c.name && trueParent !== associatedParentOf.get(c.name))
		{
			belongsToOverride.set(c.name, trueParent);
		}
	});

	// ---- Rule 4: mixin/purpose overrides inheritance placement ----
	const effectiveRootOf = new Map(rootOf);
	tagged.forEach(c =>
	{
		const overrideTarget = (c.mixins || [])
			.map(m => ASSOCIATION_MIXINS[m.name])
			.find(Boolean);
		if (overrideTarget && byName.has(overrideTarget))
		{
			const actualParent = rootOf.get(c.name);
			// Only a real, DIFFERENT inheritance parent is worth a "Belongs to" tag - a component
			// that was already its own root (no Et2* ancestor at all, e.g. Et2CustomFilterHeader
			// extends LitElement directly) has nothing distinct to point back at.
			if (actualParent && actualParent !== overrideTarget && actualParent !== c.name)
			{
				belongsToOverride.set(c.name, actualParent);
			}
			effectiveRootOf.set(c.name, overrideTarget);
		}
	});

	const effectiveVariationsOf = new Map();
	tagged.forEach(c =>
	{
		if (associatedParentOf.has(c.name))
		{
			return; // placed via rule 5 instead - see the belongsToOverride block above
		}
		const root = effectiveRootOf.get(c.name);
		if (root !== c.name)
		{
			if (!effectiveVariationsOf.has(root))
			{
				effectiveVariationsOf.set(root, []);
			}
			effectiveVariationsOf.get(root).push(c.name);
		}
	});

	// ---- Rule 6: Related family detection ----
	// Only components that ended up as top-level, non-associated roots (post rule 4) are candidates.
	const effectiveRoots = tagged.filter(c => effectiveRootOf.get(c.name) === c.name && !associatedParentOf.has(c.name));

	const byTopDir = new Map();
	effectiveRoots.forEach(r =>
	{
		const top = topDirOf(r);
		if (!top)
		{
			return;
		}
		if (!byTopDir.has(top))
		{
			byTopDir.set(top, []);
		}
		byTopDir.get(top).push(r);
	});

	const relatedGroups = new Map(); // component name -> [sibling names]
	byTopDir.forEach((members, top) =>
	{
		if (members.length < 2)
		{
			return;
		}
		const bareName = top.replace(/^Et2/, '');
		const allContain = members.every(m => m.name.includes(bareName));
		if (allContain)
		{
			members.forEach(m =>
			{
				relatedGroups.set(m.name, members.filter(x => x.name !== m.name).map(x => x.name));
			});
		}
	});

	// Explicit `@related` tags (see custom-elements-manifest.config.mjs) - for conceptual-but-not-
	// structural kinships the automatic same-directory detection above can't find (e.g.
	// Et2VfsSelectDialog "is a dialog" but doesn't actually extend Et2Dialog). Symmetrized: tagging
	// one side adds the link to both, so it only needs to be declared once.
	function addRelated(a, b)
	{
		const existing = relatedGroups.get(a) || [];
		if (!existing.includes(b))
		{
			relatedGroups.set(a, [...existing, b]);
		}
	}
	tagged.forEach(c =>
	{
		(c.relatedTags || []).forEach(targetName =>
		{
			if (!byName.has(targetName) || targetName === c.name)
			{
				return;
			}
			addRelated(c.name, targetName);
			addRelated(targetName, c.name);
		});
	});

	// ---- Rule 3 + rule 4's belongsTo: shared "Belongs to" tag, two independent triggers ----
	function belongsToOf(component)
	{
		if (belongsToOverride.has(component.name))
		{
			return belongsToOverride.get(component.name);
		}
		const top = topDirOf(component);
		return (top && FEATURE_AREAS[top]) || null;
	}

	function toRef(name)
	{
		const c = byName.get(name);
		return c ? {name: c.name, tagName: c.tagName} : null;
	}

	// Associated widgets can attach to ANY component - a root, a variation, or (recursively)
	// another associated widget - not just top-level roots. E.g. Et2ThumbnailTag's actual parent
	// is Et2SelectThumbnail, itself a variation of Et2Select, not a root; without this, it silently
	// vanished from the whole taxonomy (rule 7 says never hide a component - caught by testing
	// against the real manifest, not assumed safe).
	function associatedOf(name)
	{
		return tagged.filter(c => associatedParentOf.get(c.name) === name).map(slimComponent);
	}

	function slimComponent(component)
	{
		return {
			name: component.name,
			tagName: component.tagName,
			belongsTo: belongsToOf(component),
			related: (relatedGroups.get(component.name) || []).map(toRef).filter(Boolean),
			associated: associatedOf(component.name)
		};
	}

	// ---- Assemble categories ----
	const categories = new Map();
	function ensureCategory(name)
	{
		if (!categories.has(name))
		{
			categories.set(name, {name, icon: CATEGORY_ICONS[name] || null, entries: []});
		}
		return categories.get(name);
	}

	effectiveRoots.forEach(root =>
	{
		const cat = ensureCategory(categoryOf(root));

		const variationNames = effectiveVariationsOf.get(root.name) || [];
		const variations = variationNames.map(n => byName.get(n)).filter(Boolean).map(slimComponent);

		cat.entries.push({
			base: slimComponent(root),
			variations
		});
	});

	// ---- Controllers & Mixins ----
	const mixinCategory = ensureCategory(CATEGORIES.CONTROLLERS);
	allMixins.forEach(mixin =>
	{
		const consumedBy = tagged
			.filter(c => (c.mixins || []).some(m => m.name === mixin.name))
			.map(c => ({name: c.name, tagName: c.tagName}));

		mixinCategory.entries.push({
			base: {name: mixin.name, tagName: null, belongsTo: null, related: [], associated: []},
			variations: [],
			consumedBy
		});
	});

	// Stable ordering: entries within a category sorted by name, categories in the fixed order
	const orderedNames = Object.values(CATEGORIES);
	const result = orderedNames
		.filter(name => categories.has(name))
		.map(name =>
		{
			const cat = categories.get(name);
			cat.entries.sort((a, b) => a.base.name.localeCompare(b.base.name));
			return cat;
		});

	return {categories: result};
}

module.exports = {buildTaxonomy, CATEGORIES, CATEGORY_ICONS, FEATURE_AREAS, ASSOCIATION_MIXINS};
