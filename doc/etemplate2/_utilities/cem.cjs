const customElementsManifest = require('../../dist/custom-elements.json');
const fs = require('fs');
const path = require('path');
const customElementsManifestShoelace = require('../custom-elements-shoelace.json');

//
// Export it here so we can import it elsewhere and use the same version
//
module.exports.customElementsManifest = customElementsManifest;

//
// Gets all components from custom-elements.json and returns them in a more documentation-friendly format.
//
module.exports.getAllComponents = function ()
{
	//
	// Find a Shoelace class declaration from their custom-elements.json
	//
	// for Et2* classes, we also look recursive, if they inherit from a Shoelace class
	//
	const getSlClass = function(superclass)
	{
		let sl_class;
		if (superclass && superclass.package === "@shoelace-style/shoelace")
		{
			customElementsManifestShoelace.modules.find(module =>
				sl_class = module.declarations.find(declaration => declaration.kind === "class" && declaration.name === superclass.name));
		}
		else if (superclass && superclass.name.substring(0, 3) === "Et2")
		{
			customElementsManifest.modules.find(module =>
				sl_class = module.declarations.find(declaration => declaration.name === superclass.name));
			if (sl_class) sl_class = getSlClass(sl_class.superclass);
		}
		//console.log("getSlClass("+superclass.name+") returning ", sl_class ? sl_class.name+" with attributes: "+sl_class.attributes?.map(attribute => attribute.name).join(", ") : "undefined");
		return sl_class;
	}
	//
	// Sort by not deprecated and name
	//
	const compareNotDeprecatedAndName = function(a, b)
	{
		if (a.deprecated && !b.deprecated) return 1;
		if (!a.deprecated && b.deprecated) return -1;
		if (a.name[0] === '_' && b.name[0] !== '_') return 1;
		if (a.name[0] !== '_' && b.name[0] === '_') return -1;
		return a.name.localeCompare(b.name);
	}
	const debug='declaration.name';	// set to declaration.name to get more logging for that component
	const allComponents = [];

	customElementsManifest.modules?.forEach(module =>
	{
		module.declarations?.forEach(declaration =>
		{
			if (declaration.customElement)
			{
				// check if we have a Shoelace superclass
				const sl_class = declaration.superclass ? getSlClass(declaration.superclass) : undefined;
				if (debug === declaration.name) console.log(declaration.name+": superclass=", declaration.superclass, sl_class ? "found: "+sl_class.name : "not found");

				// Generate the dist path based on the src path and attach it to the component
				declaration.path = module.path.replace(/^src\//, 'dist/').replace(/\.ts$/, '.js');

				// Remove members that are private or don't have a description
				//
				let members = declaration.members?.filter(member => member.description && member.privacy !== 'private') || [];
				// add non-private and not overwritten Shoelace superclass members
				if (debug === declaration.name) console.log("found members: "+members.map(member => member.name).join(", "));
				if (sl_class)
				{
					const sl_members = sl_class.members?.filter(member =>
						member.description && member.privacy !== 'private' && !members.find(egw => member.name === egw.name))/*.map(member => {
							return {...member, inheritedFrom: {name: sl_class.name, module: "@shoelace-style/shoelace"}};
						})*/;
					if (debug === declaration.name)  console.log("adding members from "+sl_class.name+": "+sl_members.map(member => member.name).join(", "));
					members = members.concat(sl_members);
				}
				let methods = members?.filter(prop => prop.kind === 'method' && prop.privacy !== 'private') || [];
				if (declaration.name === "Et2ButtonScroll") console.log("found methods: "+methods.map(method => method.name).join(", "));
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

					return prop.kind === 'field' && prop.privacy !== 'private';
				}).sort(compareNotDeprecatedAndName);
				if (debug === declaration.name) console.log("found properties: "+properties.map(property => property.name).join(", "));
				allComponents.push({
					...declaration,
					methods,
					properties,
					attributes: declaration.attributes?.concat(sl_class?.attributes?.filter(attribute => !declaration.attributes.find(attr => attr.name === attribute.name))
						/*.map(attribute => {
							return {...attribute, inheritedFrom: {name: sl_class.name, module: "@shoelace-style/shoelace"}};
						})*/)
				});
				if (debug === declaration.name) console.log("added attributes", allComponents[allComponents.length - 1].attributes);
			}
		});
	});
	console.log('Build dependency graphs');
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
	console.log('Add custom docs');
	// Add custom docs - not monitored for file changes
	allComponents.forEach(component =>
	{
		// Check for custom docs
		const docPath = path.join('..', '..', path.dirname(component.path), component.name + ".md");

		// Stick it in a variable so we can use the content filters
		if (fs.existsSync(path.resolve(docPath)))
		{
			fs.readFile(docPath, (err, data) => component.content = data.toString());
		}
	})
	console.log("return allComponentes sorted by name")
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

module.exports.getShoelaceVersion = function ()
{
	const shoelace = "@shoelace-style/shoelace"

	const package = JSON.parse(fs.readFileSync('../../package.json', "utf8")) || {dependencies: {}}
	return package.dependencies[shoelace] || "";
}