const customElementsManifest = require('../../dist/custom-elements.json');
const fs = require('fs');
const path = require('path');
const customElementsManifestShoelace = require('../custom-elements-shoelace.json');

//
// Export it here so we can import it elsewhere and use the same version
//
module.exports.customElementsManifest = customElementsManifest;

//
// Every LitElement has this, but it was only ever hand-added as a hardcoded extra row in
// component.njk's "own properties" table - which only rendered when a widget had at least one
// other own property, so it silently disappeared from any widget/variation whose own properties
// list was empty (e.g. Et2SelectCountry). Tagging it with inheritedFrom here instead routes it
// through the normal own/inherited split, so every widget gets it the same way: grouped under a
// "LitElement" ancestor in the collapsed Inherited properties section.
//
const LIT_UPDATE_COMPLETE_PROPERTY = {
	kind: 'field',
	name: 'updateComplete',
	description: 'A read-only promise that resolves when the component has finished updating.',
	type: {text: 'Promise<boolean>'},
	inheritedFrom: {name: 'LitElement', module: 'lit'}
};

//
// Splits a members/attributes array into what a class declares itself ("own") vs. what it only
// inherits (grouped by ancestor, via the inheritedFrom field the manifest already provides on
// every inherited entry). Used to keep widget pages from repeating the same wall of inherited
// id/label/statustext/... properties on every single subclass page.
//
// `private` was already excluded from the docs; `protected` wasn't, even though it's just as
// much an internal implementation detail (e.g. Et2Date's `_inputNode`/`_valueNode` - real widget
// internals, not part of the public API a template author or extending widget's author needs).
function isPublicPrivacy(member)
{
	return member.privacy !== 'private' && member.privacy !== 'protected';
}

function splitOwnAndInherited(members)
{
	// declaration.attributes?.concat(...undefined...) can leave a literal `undefined` element in
	// the array when a component has no Shoelace superclass - filter it out before inspecting
	// .inheritedFrom on each entry.
	members = (members || []).filter(Boolean);
	const own = members.filter(m => !m.inheritedFrom);
	const byAncestor = new Map();
	members.filter(m => m.inheritedFrom).forEach(m =>
	{
		const key = m.inheritedFrom.name;
		if (!byAncestor.has(key))
		{
			byAncestor.set(key, {name: key, module: m.inheritedFrom.module, members: []});
		}
		byAncestor.get(key).members.push(m);
	});
	return {own, inherited: [...byAncestor.values()]};
}

//
// Sort by not deprecated and name - module-level so both getAllComponents() and getAllMixins()
// can use it.
//
function compareNotDeprecatedAndName(a, b)
{
	if (a.deprecated && !b.deprecated) return 1;
	if (!a.deprecated && b.deprecated) return -1;
	if (a.name[0] === '_' && b.name[0] !== '_') return 1;
	if (a.name[0] !== '_' && b.name[0] === '_') return -1;
	return a.name.localeCompare(b.name);
}

//
// Gets all components from custom-elements.json and returns them in a more documentation-friendly format.
//
module.exports.getAllComponents = function ()
{
	//
	// Find a Shoelace class declaration from their custom-elements.json
	//
	// for Et2* classes, we also look recursive, if they inherit from a Shoelace class
	// or a (not included) Readonly or Mobile class, in with case we return the regular Et2-class
	//
	const getSlClass = function(superclass, debug)
	{
		let sl_class;
		if (superclass && superclass.package === "@shoelace-style/shoelace")
		{
			customElementsManifestShoelace.modules.find(module =>
				sl_class = module.declarations.find(declaration => declaration.kind === "class" && declaration.name === superclass.name));
		}
		else if (superclass && typeof superclass.name === 'string' && superclass.name.substring(0, 3) === "Et2")
		{
			const name = superclass.name.replace(/(Readonly|Mobile)$/, '');
			customElementsManifest.modules.find(module =>
				sl_class = module.declarations.find(declaration => declaration.name === name));
			if (sl_class && name === superclass.name) sl_class = getSlClass(sl_class.superclass);
		}
		if (debug) console.log("getSlClass("+superclass.name+") returning ", sl_class ? sl_class.name+" with attributes: "+sl_class.attributes?.map(attribute => attribute.name).join(", ") : "undefined");
		return sl_class;
	}
	const debug='';	// set to declaration.name to get more logging for that component
	const allComponents = [];

	customElementsManifest.modules?.forEach(module =>
	{
		module.declarations?.forEach(declaration =>
		{
			if (declaration.customElement && !declaration.tagName)
			{
				return;
			}

			if (declaration.customElement)
			{
				// check if we have a Shoelace superclass
				const sl_class = declaration.superclass ? getSlClass(declaration.superclass, debug === declaration.name) : undefined;
				if (debug === declaration.name) console.log(declaration.name+": superclass=", declaration.superclass, sl_class ? "found: "+sl_class.name : "not found");

				// Generate the dist path based on the src path and attach it to the component
				declaration.path = module.path.replace(/^src\//, 'dist/').replace(/\.ts$/, '.js');

				// Remove members that are private or don't have a description
				//
				let members = declaration.members?.filter(member => member.description && isPublicPrivacy(member)) || [];
				// add non-private and not overwritten Shoelace superclass members
				if (debug === declaration.name) console.log("found members: "+members.map(member => member.name).join(", "));
				if (sl_class)
				{
					// Tag these as inherited-from-Shoelace (was previously commented out, so every
					// Shoelace-merged member silently counted as "own" - e.g. Et2ButtonTimestamper
					// only declares target/format/timezone but showed all of SlButton's properties
					// as its own, because they carried no inheritedFrom for splitOwnAndInherited to
					// find). Confirmed against real data before fixing, not assumed.
					const sl_members = (sl_class.members?.filter(member =>
						member.description && isPublicPrivacy(member) && !members.find(egw => member.name === egw.name)) || [])
						.map(member => ({...member, inheritedFrom: {name: sl_class.name, module: "@shoelace-style/shoelace"}}));
					if (debug === declaration.name)  console.log("adding members from "+sl_class.name+": "+sl_members.map(member => member.name).join(", "));
					members = members.concat(sl_members);
				}
				let methods = members?.filter(prop => prop.kind === 'method' && isPublicPrivacy(prop)) || [];
				if (debug === declaration.name) console.log("found methods: "+methods.map(method => method.name).join(", "));
				// add non-private and not overwritten Shoelace superclass methods
				/* ToDo disabled, as it gives an error later (only copies 8 files and generates none)
				if (sl_class)
				{
					const sl_methods = sl_class.members?.filter(prop =>
						prop.kind === 'method' && prop.privacy !== 'private' && !methods.find(egw => prop.name === egw.name))/*.map(method => {
							return {...method, inheritedFrom: {name: sl_class.name, module: "@shoelace-style/shoelace"}};
						});
					if (debug === declaration.name) console.log("adding methods from "+sl_class.name+": "+sl_methods.map(method => method.name).join(", "));
					methods = methods.concat(sl_methods);
				}*/
				methods = methods.sort(compareNotDeprecatedAndName);
				const properties = members?.filter(prop =>
				{
					if (debug === declaration.name) console.log("Asserting "+declaration.name+" property", prop);
					// Look for a corresponding attribute
					const attribute = (declaration.attributes||[]).concat(sl_class?.attributes || []).find(attr => attr.fieldName === prop.name);
					if (attribute)
					{
						prop.attribute = attribute.name || attribute.fieldName;
					}

					return prop.kind === 'field' && isPublicPrivacy(prop);
				}).sort(compareNotDeprecatedAndName);
				properties.push(LIT_UPDATE_COMPLETE_PROPERTY);
				if (debug === declaration.name) console.log("found properties: "+properties.map(property => property.name).join(", "));
				const attributes = declaration.attributes?.concat(sl_class?.attributes?.filter(attribute => !declaration.attributes.find(attr => attr.name === attribute.name))
					.map(attribute => {
						return {...attribute, inheritedFrom: {name: sl_class.name, module: "@shoelace-style/shoelace"}};
					}) || []);
				allComponents.push({
					...declaration,
					methods,
					properties,
					attributes,
					// Own vs. inherited split (grouped by ancestor) - the manifest already tags every
					// inherited member/attribute with inheritedFrom, this just partitions on it so
					// component.njk can render "own" in full and "inherited" collapsed per-ancestor
					// instead of repeating the whole inherited wall of text on every subclass page.
					ownProperties: splitOwnAndInherited(properties).own,
					inheritedProperties: splitOwnAndInherited(properties).inherited,
					ownMethods: splitOwnAndInherited(methods).own,
					inheritedMethods: splitOwnAndInherited(methods).inherited,
					ownAttributes: splitOwnAndInherited(attributes).own,
					inheritedAttributes: splitOwnAndInherited(attributes).inherited
				});
				if (debug === declaration.name) console.log("added attributes", allComponents[allComponents.length - 1].attributes);
			}
		});
	});
	if (debug) console.log('Build dependency graphs');
	// Build dependency graphs
	allComponents.forEach(component =>
	{
		const dependencies = [];

		// Recursively fetch sub-dependencies
		function getDependencies(tag)
		{
			const cmp = allComponents.find(c => c.tagName === tag);
			if (!cmp || !Array.isArray(component.dependencies))
			{
				return;
			}

			cmp.dependencies?.forEach(dependentTag =>
			{
				if (!dependencies.includes(dependentTag))
				{
					dependencies.push(dependentTag);
				}
				getDependencies(dependentTag);
			});
		}

		getDependencies(component.tagName);

		component.dependencies = dependencies.sort();
	});
	if (debug) console.log('Add custom docs');
	// Add custom docs - not monitored for file changes
	allComponents.forEach(component =>
	{
		// Check for custom docs
		const docPath = path.join('..', '..', path.dirname(component.path), component.name + ".md");

		// Stick it in a variable so we can use the content filters
		if (fs.existsSync(path.resolve(docPath)))
		{
			component.content = fs.readFileSync(docPath, 'utf8');
		}
	})
	if (debug) console.log("return allComponentes sorted by name")
	// Sort by name
	return allComponents.sort((a, b) =>
	{
		if (a.name < b.name)
		{
			return -1;
		}
		if (a.name > b.name)
		{
			return 1;
		}
		return 0;
	});
};

//
// Gets all mixins/controllers (declarations with no tagName - Et2InputWidget, FilterMixin, ...)
// in the same documentation-friendly shape as getAllComponents(), for the "Controllers & Mixins"
// sidebar category. Kept as a SEPARATE function rather than widening getAllComponents()'s own
// customElement-only gate, so _data/components.json and every existing consumer of it (the
// default_component.njk pagination, meta.components, ...) is unaffected - mixins get a home
// without changing the shape or contents of the data those already depend on.
//
module.exports.getAllMixins = function ()
{
	const allMixins = [];

	customElementsManifest.modules?.forEach(module =>
	{
		module.declarations?.forEach(declaration =>
		{
			if (declaration.kind !== 'mixin')
			{
				return;
			}

			declaration.path = module.path.replace(/^src\//, 'dist/').replace(/\.ts$/, '.js');

			const members = declaration.members?.filter(member => member.description && isPublicPrivacy(member)) || [];
			const methods = members.filter(prop => prop.kind === 'method' && isPublicPrivacy(prop)).sort(compareNotDeprecatedAndName);
			const properties = members.filter(prop => prop.kind === 'field' && isPublicPrivacy(prop)).sort(compareNotDeprecatedAndName);

			allMixins.push({
				...declaration,
				methods,
				properties,
				ownProperties: splitOwnAndInherited(properties).own,
				inheritedProperties: splitOwnAndInherited(properties).inherited,
				ownMethods: splitOwnAndInherited(methods).own,
				inheritedMethods: splitOwnAndInherited(methods).inherited
			});
		});
	});

	allMixins.forEach(mixin =>
	{
		const docPath = path.join('..', '..', path.dirname(mixin.path), mixin.name + ".md");
		if (fs.existsSync(path.resolve(docPath)))
		{
			mixin.content = fs.readFileSync(docPath, 'utf8');
		}
	});

	return allMixins.sort((a, b) => a.name.localeCompare(b.name));
};

module.exports.getShoelaceVersion = function ()
{
	const shoelace = "@shoelace-style/shoelace"

	const package = JSON.parse(fs.readFileSync('../../package.json', "utf8")) || {dependencies: {}}
	return package.dependencies[shoelace] || "";
}