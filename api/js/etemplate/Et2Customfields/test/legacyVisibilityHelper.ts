type Customfield = {
	label? : string;
	type? : string;
	type2? : string | string[];
	tab? : string | null;
	private? : string | boolean | any[];
};

export interface LegacyVisibilityInput
{
	customfields : Record<string, Customfield>;
	fields? : Record<string, boolean> | string | null;
	exclude? : string | null;
	typeFilter? : string | string[] | "previous" | null;
	tab? : string | null;
	defaultTabMatch? : "" | "-private" | "-non-private" | null;
}

let previousTypeFilter : string[] | null = null;

const normalizeFields = (fields : LegacyVisibilityInput["fields"]) =>
{
	if(!fields)
	{
		return {};
	}
	if(typeof fields === "string")
	{
		const result : Record<string, boolean> = {};
		fields.split(",").map((name) => name.trim()).filter(Boolean).forEach((name) => result[name] = true);
		return result;
	}
	return {...fields};
};

const hasPrivate = (field : Customfield) =>
{
	const value = field.private as any;
	if(Array.isArray(value))
	{
		return value.length > 0;
	}
	if(typeof value === "string")
	{
		return value.length > 0;
	}
	return !!value;
};

/**
 * Legacy-logic helper copied from `et2_extension_customfields` constructor branches.
 * It is intentionally used as a baseline contract reference for migration tests.
 */
export function legacyVisibility(input : LegacyVisibilityInput) : Record<string, boolean>
{
	const options = {
		customfields: input.customfields || {},
		fields: normalizeFields(input.fields),
		exclude: input.exclude || "",
		typeFilter: input.typeFilter ?? null,
		tab: input.tab ?? null,
		defaultTabMatch: input.defaultTabMatch ?? null
	};
	const exclude = new Set(String(options.exclude).split(",").map((name) => name.trim()).filter(Boolean));

	if(options.typeFilter === "previous")
	{
		options.typeFilter = previousTypeFilter;
	}
	if(typeof options.typeFilter === "string")
	{
		options.typeFilter = options.typeFilter.split(",").map((type) => type.trim()).filter(Boolean);
	}
	previousTypeFilter = Array.isArray(options.typeFilter) ? options.typeFilter : null;

	if(Array.isArray(options.typeFilter) && options.typeFilter.length)
	{
		for(const fieldName of Object.keys(options.customfields))
		{
			const field = options.customfields[fieldName];
			if(!field.type2 || field.type2.length === 0 || field.type2 === "0")
			{
				options.fields[fieldName] = true;
				continue;
			}
			const types = typeof field.type2 === "string" ? field.type2.split(",") : field.type2;
			options.fields[fieldName] = false;
			for(const type of types)
			{
				if(options.typeFilter.includes(type))
				{
					options.fields[fieldName] = true;
				}
			}
		}
	}

	const hasExplicitFields = Object.keys(options.fields).length > 0;
	if(!hasExplicitFields)
	{
		for(const fieldName of Object.keys(options.customfields))
		{
			if(exclude.has(fieldName))
			{
				options.fields[fieldName] = false;
				continue;
			}
			const field = options.customfields[fieldName];
			if(field.tab)
			{
				options.fields[fieldName] = field.tab === options.tab;
			}
			else if(options.defaultTabMatch !== null)
			{
				if(hasPrivate(field))
				{
					options.fields[fieldName] = options.defaultTabMatch !== "-non-private";
				}
				else
				{
					options.fields[fieldName] = options.defaultTabMatch !== "-private";
				}
			}
			else
			{
				options.fields[fieldName] = true;
			}
		}
		return options.fields;
	}

	for(const fieldName of Object.keys(options.customfields))
	{
		const field = options.customfields[fieldName];
		if(Array.isArray(options.typeFilter) && options.typeFilter.length && options.fields[fieldName] !== true)
		{
			continue;
		}
		if(exclude.has(fieldName))
		{
			options.fields[fieldName] = false;
		}
		else if(options.defaultTabMatch !== null ? !!field.tab : field.tab !== options.tab && !!options.tab)
		{
			options.fields[fieldName] = false;
		}
		else if(options.defaultTabMatch !== null)
		{
			if(hasPrivate(field))
			{
				options.fields[fieldName] = options.defaultTabMatch !== "-non-private";
			}
			else if(options.fields[fieldName])
			{
				options.fields[fieldName] = options.defaultTabMatch !== "-private";
			}
		}
	}

	for(const fieldName of Object.keys(options.customfields))
	{
		if(typeof options.fields[fieldName] === "undefined")
		{
			options.fields[fieldName] = false;
		}
	}
	return options.fields;
}

export const sampleCustomfields : Record<string, Customfield> = {
	cf_text: {label: "Text", type: "text", type2: "task", private: "", tab: null},
	cf_project: {label: "Project", type: "select", type2: "project,task", private: "", tab: "extra"},
	cf_private: {label: "Private", type: "select", type2: "0", private: "1", tab: null},
	cf_file: {label: "File", type: "filemanager", type2: "", private: "", tab: null}
};
