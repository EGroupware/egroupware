export interface Et2CustomfieldDefinition
{
	name? : string;
	label? : string;
	type? : string;
	type2? : string | string[];
	tab? : string | null;
	private? : string | string[] | boolean | null;
	values? : Record<string, any>;
}

export interface Et2CustomfieldSelectionItem
{
	name : string;
	label : string;
	visible : boolean;
}

export interface Et2CustomfieldsControllerOptions
{
	customfields : Record<string, Et2CustomfieldDefinition> | Et2CustomfieldDefinition[];
	fields? : Record<string, boolean> | string | null;
	exclude? : string | null;
	typeFilter? : string | string[] | "previous" | null;
	tab? : string | null;
	mode? : "customfields" | "customfields-list" | "customfields-filters" | "nextmatch-customfields";
	defaultTabMatch? : "" | "-private" | "-non-private" | null;
}

/**
 * Merge customfield widget settings from local/global modification sources into attrs.
 *
 * This is the standard server-to-client delivery path for customfield definitions:
 * - local widget modifications (`modifications[widgetId]`) first
 * - global shared settings (`modifications["~custom_fields~"]`) as fallback
 *
 * Only missing attrs are filled; explicit widget attrs remain authoritative.
 *
 * @returns true if attrs changed
 */
export function mergeCustomfieldSettingsFromSources(
	attrs : Record<string, any>,
	localData : Record<string, any> = {},
	globalData : Record<string, any> = {}
) : boolean
{
	let changed = false;
	const isEmptyObject = (value : any) => !!value && typeof value === "object" && !Object.keys(value).length;
	const isMissingFields = (value : any) =>
		typeof value === "undefined" || value === null || value === "" || isEmptyObject(value);
	const mergeMissing = (source : Record<string, any>) =>
	{
		if(!source || typeof source !== "object")
		{
			return;
		}
		for(const key of Object.keys(source))
		{
			if(key === "customfields")
			{
				const current = attrs.customfields;
				const missing = !current || (typeof current === "object" && !Object.keys(current).length);
				if(missing && source.customfields)
				{
					attrs.customfields = source.customfields;
					changed = true;
				}
				continue;
			}
			if(key === "fields")
			{
				if(isMissingFields(attrs.fields) && source.fields)
				{
					attrs.fields = source.fields;
					changed = true;
				}
				continue;
			}
			if((typeof attrs[key] === "undefined" || attrs[key] === null) && typeof source[key] !== "undefined")
			{
				attrs[key] = source[key];
				changed = true;
			}
		}
	};

	mergeMissing(localData);
	mergeMissing(globalData);
	return changed;
}

/**
 * Shared customfield filtering/visibility state used by widgets and nextmatch header.
 *
 * It intentionally mirrors legacy `et2_extension_customfields` filtering behaviour
 * for field visibility decisions, but exposes clear plain-object state.
 */
export class Et2CustomfieldsController
{
	private static previousTypeFilter : string[] | null = null;

	private readonly customfields : Record<string, Et2CustomfieldDefinition>;
	private readonly fieldAliases : Map<string, string> = new Map();
	private readonly mode : "customfields" | "customfields-list" | "customfields-filters" | "nextmatch-customfields";
	private readonly tab : string | null;
	private readonly defaultTabMatch : "" | "-private" | "-non-private" | null;
	private readonly exclude : Set<string>;
	private readonly typeFilter : string[] | null;
	private readonly explicitFields : Record<string, boolean>;
	private visibility : Record<string, boolean>;

	constructor(options : Et2CustomfieldsControllerOptions)
	{
		this.customfields = this._normalizeCustomfields(options.customfields || {});
		this.mode = options.mode || "customfields";
		this.tab = typeof options.tab === "string" && options.tab.length ? options.tab : null;
		this.defaultTabMatch = options.defaultTabMatch ?? null;
		this.exclude = this._normalizeExclude(options.exclude);
		this.typeFilter = this._normalizeTypeFilter(options.typeFilter ?? null);
		this.explicitFields = this._normalizeExplicitFields(options.fields);
		this.visibility = this._resolveInitialVisibility();
	}

	getVisibleMap() : Record<string, boolean>
	{
		return {...this.visibility};
	}

	getVisibleFieldNames() : string[]
	{
		return Object.keys(this.customfields).filter((name) => this.visibility[name] === true);
	}

	getSelectionItems() : Et2CustomfieldSelectionItem[]
	{
		return Object.keys(this.customfields).map((name) =>
		{
			const field = this.customfields[name] || {};
			const fieldName = this._canonicalFieldName(name, field);
			return {
				name: fieldName,
				label: String(field.label || field.name || fieldName),
				visible: this.visibility[fieldName] === true
			};
		});
	}

	setVisibility(fields : Record<string, boolean>)
	{
		const next : Record<string, boolean> = {};
		for(const fieldName of Object.keys(this.customfields))
		{
			next[fieldName] = fields[fieldName] === true || fields[this._lookupAlias(fieldName)] === true;
		}
		this.visibility = next;
	}

	isAllowedFilterField(field : Et2CustomfieldDefinition, apps : Record<string, any>) : boolean
	{
		const type = String(field?.type || "");
		return type.startsWith("select") || (
			type !== "filemanager" &&
			typeof apps[type] !== "undefined"
		);
	}

	private _normalizeExplicitFields(fields : Record<string, boolean> | string | null | undefined) : Record<string, boolean>
	{
		if(!fields)
		{
			return {};
		}
		if(typeof fields === "string")
		{
			const result : Record<string, boolean> = {};
			fields.split(",")
				.map((name) => name.trim())
				.filter(Boolean)
				.forEach((name) => result[this._lookupAlias(name)] = true);
			return result;
		}
		const result : Record<string, boolean> = {};
		for(const name of Object.keys(fields))
		{
			result[this._lookupAlias(name)] = fields[name] === true;
		}
		return result;
	}

	private _normalizeCustomfields(customfields : Record<string, Et2CustomfieldDefinition> | Et2CustomfieldDefinition[]) : Record<string, Et2CustomfieldDefinition>
	{
		const normalized : Record<string, Et2CustomfieldDefinition> = {};
		if(Array.isArray(customfields))
		{
			for(let i = 0; i < customfields.length; i++)
			{
				let field : any = customfields[i];
				let sourceKey : string | null = null;
				// Also accept array entries from Object.entries() shape: [key, value]
				if(Array.isArray(field) && field.length >= 2 && typeof field[0] !== "undefined")
				{
					sourceKey = String(field[0]);
					field = field[1];
				}
				if(!field)
				{
					continue;
				}
				const name = this._canonicalFieldName(sourceKey || String(i), field);
				normalized[name] = field;
				if(sourceKey)
				{
					this.fieldAliases.set(sourceKey, name);
				}
				this.fieldAliases.set(name, name);
			}
			return normalized;
		}
		for(const key of Object.keys(customfields || {}))
		{
			const field = customfields[key];
			if(!field)
			{
				continue;
			}
			const normalizedName = this._canonicalFieldName(key, field);
			normalized[normalizedName] = field;
			this.fieldAliases.set(key, normalizedName);
			this.fieldAliases.set(normalizedName, normalizedName);
		}
		return normalized;
	}

	private _normalizeExclude(exclude : string | null | undefined) : Set<string>
	{
		return new Set(
			String(exclude || "")
				.split(",")
				.map((name) => name.trim())
				.filter(Boolean)
				.map((name) => this._lookupAlias(name))
		);
	}

	private _lookupAlias(name : string) : string
	{
		const key = String(name || "").trim();
		if(!key)
		{
			return key;
		}
		return this.fieldAliases.get(key) || key;
	}

	private _canonicalFieldName(fallback : string, field : Et2CustomfieldDefinition) : string
	{
		return String(field?.name || "").trim() || String(fallback || "").trim();
	}

	private _normalizeTypeFilter(typeFilter : string | string[] | "previous" | null) : string[] | null
	{
		let resolved : string | string[] | null = typeFilter;
		if(typeFilter === "previous")
		{
			resolved = Et2CustomfieldsController.previousTypeFilter;
		}
		if(typeof resolved === "string")
		{
			resolved = resolved.split(",").map((type) => type.trim()).filter(Boolean);
		}
		if(Array.isArray(resolved))
		{
			const normalized = resolved.map((type) => String(type).trim()).filter(Boolean);
			Et2CustomfieldsController.previousTypeFilter = normalized.length ? normalized : null;
			return normalized.length ? normalized : null;
		}
		Et2CustomfieldsController.previousTypeFilter = null;
		return null;
	}

	private _hasPrivateFlag(field : Et2CustomfieldDefinition) : boolean
	{
		const privateValue = field?.private as any;
		if(Array.isArray(privateValue))
		{
			return privateValue.length > 0;
		}
		if(typeof privateValue === "string")
		{
			return privateValue.length > 0;
		}
		return !!privateValue;
	}

	private _matchesTypeFilter(field : Et2CustomfieldDefinition) : boolean
	{
		if(!this.typeFilter || !this.typeFilter.length)
		{
			return true;
		}
		const type2 = field?.type2;
		if(!type2 || type2 === "0" || (Array.isArray(type2) && type2.length === 0))
		{
			return true;
		}
		const fieldTypes = Array.isArray(type2) ? type2 : String(type2).split(",").map((type) => type.trim()).filter(Boolean);
		return fieldTypes.some((type) => this.typeFilter!.includes(type));
	}

	private _resolveDefaultTabVisibility(field : Et2CustomfieldDefinition, current : boolean) : boolean
	{
		const isPrivate = this._hasPrivateFlag(field);
		if(isPrivate)
		{
			return this.defaultTabMatch !== "-non-private";
		}
		if(current)
		{
			return this.defaultTabMatch !== "-private";
		}
		return current;
	}

	private _resolveInitialVisibility() : Record<string, boolean>
	{
		const allFields = Object.keys(this.customfields);
		const explicitFieldsProvided = Object.keys(this.explicitFields).length > 0;

		const baseFields : Record<string, boolean> = {};
		if(explicitFieldsProvided)
		{
			Object.assign(baseFields, this.explicitFields);
		}
		else if(this.typeFilter?.length)
		{
			for(const fieldName of allFields)
			{
				baseFields[fieldName] = this._matchesTypeFilter(this.customfields[fieldName]);
			}
		}

		const fieldsProvided = Object.keys(baseFields).length > 0;
		const visibility : Record<string, boolean> = {};

		if(!fieldsProvided)
		{
			for(const fieldName of allFields)
			{
				const field = this.customfields[fieldName] || {};
				let visible = true;
				if(this.exclude.has(fieldName))
				{
					visible = false;
				}
				else if(this.mode === "customfields-filters")
				{
					visible = true;
				}
				else if(field.tab)
				{
					visible = field.tab === this.tab;
				}
				else if(this.defaultTabMatch !== null)
				{
					visible = this._resolveDefaultTabVisibility(field, true);
				}
				visibility[fieldName] = visible;
			}
			return visibility;
		}

		for(const fieldName of allFields)
		{
			const field = this.customfields[fieldName] || {};
			let visible = baseFields[fieldName] === true;

			if(this.typeFilter?.length && baseFields[fieldName] !== true)
			{
				visibility[fieldName] = false;
				continue;
			}
			if(this.exclude.has(fieldName))
			{
				visible = false;
			}
			else if(this.defaultTabMatch !== null ? !!field.tab : (!!this.tab && field.tab !== this.tab))
			{
				visible = false;
			}
			else if(this.defaultTabMatch !== null)
			{
				visible = this._resolveDefaultTabVisibility(field, visible);
			}

			visibility[fieldName] = visible;
		}
		return visibility;
	}
}
