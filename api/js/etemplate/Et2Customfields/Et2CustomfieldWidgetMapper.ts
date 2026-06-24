import type {Et2CustomfieldDefinition} from "./Et2CustomfieldsController";

export type Et2CustomfieldRenderContext = "field" | "list" | "filters" | "row";

export interface Et2CustomfieldWidgetMappingOptions
{
	context : Et2CustomfieldRenderContext;
	readonly? : boolean;
	apps? : Record<string, any>;
	prefix? : string;
}

export interface Et2CustomfieldWidgetMapping
{
	tagName : string;
	attrs : Record<string, any>;
}

export function mapCustomfieldToWidget(
	fieldName : string,
	field : Et2CustomfieldDefinition & Record<string, any>,
	value : any,
	options : Et2CustomfieldWidgetMappingOptions
) : Et2CustomfieldWidgetMapping | null
{
	const context = options.context || "list";
	const prefix = options.prefix || "#";
	const apps = options.apps || {};
	const attrs : Record<string, any> = {
		id: prefix + fieldName,
		label: field?.label || fieldName,
		noLang: true,
		readonly: options.readonly === true,
		statustext: field?.help || "",
		value: value ?? ""
	};
	if(typeof field?.needed !== "undefined")
	{
		attrs.needed = field.needed;
	}
	if(attrs.readonly === true)
	{
		delete attrs.needed;
	}

	if(context === "filters")
	{
		if(!isAllowedCustomfieldFilter(field, apps))
		{
			return null;
		}
		attrs.emptyLabel = attrs.emptyLabel || "all";
		attrs.needed = false;
		attrs.multiple = true;
		delete attrs.rows;
	}

	const sourceType = String(field?.type || "text").replace(/_/g, "-");
	const isAppBacked = typeof apps[sourceType] !== "undefined";
	let widgetType = sourceType;

	if(isAppBacked)
	{
		widgetType = "link-entry";
		if(sourceType === "filemanager")
		{
			return mapFilemanagerField(fieldName, field, attrs);
		}
		delete attrs.label;
		attrs[attrs.readonly ? "app" : "onlyApp"] = typeof field.only_app === "undefined"
			? sourceType
			: (field.onlyApp ?? field.only_app);
		attrs.searchOptions = {filter: field.values || {}};
		return finalizeMapping(widgetType, attrs);
	}

	switch(sourceType)
	{
		case "text":
			delete attrs.label;
			widgetType = Number(field.rows) > 1 ? "textarea" : "textbox";
			if(Number(field.rows) > 1)
			{
				attrs.rows = field.rows;
			}
			if(field.len)
			{
				attrs.size = field.len;
				if(Number(field.rows) === 1)
				{
					attrs.maxlength = field.len;
				}
			}
			break;

		case "passwd":
			delete attrs.label;
			widgetType = "textbox";
			attrs.type = "password";
			Object.assign(attrs, {
				viewable: field.values?.viewable ?? true,
				plaintext: field.values?.plaintext ?? false,
				suggest: field.values?.suggest ?? 16,
				autocomplete: field.values?.autocomplete ?? "new-password"
			});
			break;

		case "serial":
			delete attrs.label;
			widgetType = "textbox";
			attrs.readonly = true;
			break;

		case "int":
			delete attrs.label;
			widgetType = "number";
			attrs.precision = 0;
			break;

		case "float":
			delete attrs.label;
			widgetType = "number";
			if(field.len)
			{
				attrs.size = field.len;
			}
			break;

		case "select":
			delete attrs.label;
			applySelectSettings(field, attrs);
			break;

		case "select-account":
			attrs.empty_label = "Select";
			if(field.account_type)
			{
				attrs.account_type = field.account_type;
			}
			delete attrs.label;
			applySelectSettings(field, attrs);
			break;

		case "date":
			attrs.data_format = field.values?.format || "Y-m-d";
			break;

		case "date-time":
			attrs.data_format = field.values?.format || "Y-m-d H:i:s";
			break;

		case "htmlarea":
			attrs.config = {
				...(field.config || {}),
				toolbarStartupExpanded: false
			};
			if(field.len)
			{
				attrs.config.width = field.len + "px";
			}
			attrs.config.height = ((Number(field.rows) > 0 ? Number(field.rows) : 5) * 16) + "px";
			break;

		case "radio":
			delete attrs.label;
			widgetType = "select";
			attrs.select_options = normalizeCustomfieldOptions(withoutEmptyOption(field.values || {}));
			if(field.values && field.values[""])
			{
				attrs.label = field.values[""];
			}
			break;

		case "checkbox":
			if(attrs.readonly && context !== "field")
			{
				attrs.ro_true = field.label;
			}
			if(Object.prototype.hasOwnProperty.call(field, "ro_true"))
			{
				attrs.ro_true = field.ro_true;
			}
			if(Object.prototype.hasOwnProperty.call(field, "ro_false"))
			{
				attrs.ro_false = field.ro_false;
			}
			break;

		case "button":
			if(context !== "field" || attrs.readonly)
			{
				return null;
			}
			attrs.label = field.label;
			if(field.values && typeof field.values === "object")
			{
				const first = Object.keys(field.values)[0];
				if(first)
				{
					attrs.label = first;
					attrs.onclick = field.values[first];
				}
			}
			break;

		case "filemanager":
			return context === "filters" ? null : mapFilemanagerField(fieldName, field, attrs);

		case "url":
			if(context !== "field")
			{
				attrs.label = field.label;
			}
			break;

		default:
			applyValueSettingsToAttrs(field, attrs, widgetType);
			break;
	}

	if(sourceType !== "select" && sourceType !== "select-account" && sourceType !== "radio")
	{
		applyValueSettingsToAttrs(field, attrs, widgetType);
	}
	return finalizeMapping(widgetType, attrs);
}

export function isAllowedCustomfieldFilter(
	field : Et2CustomfieldDefinition & Record<string, any>,
	apps : Record<string, any> = {}
) : boolean
{
	const type = String(field?.type || "");
	return type.startsWith("select") || (
		type !== "filemanager" &&
		typeof apps[type] !== "undefined"
	);
}

export function normalizeCustomfieldOptions(source : any) : Array<{value : string; label : string}>
{
	if(!source || typeof source !== "object")
	{
		return [];
	}
	if(Array.isArray(source))
	{
		return source.map((option) =>
		{
			if(option && typeof option === "object")
			{
				return {
					value: String(option.value ?? ""),
					label: String(option.label ?? option.value ?? "")
				};
			}
			return {value: String(option), label: String(option)};
		});
	}
	return Object.keys(source)
		.filter((key) => key !== "@")
		.map((key) => ({
			value: key,
			label: String(source[key])
		}));
}

export function applyCustomfieldWidgetMapping(element : Element | undefined, mapping : Et2CustomfieldWidgetMapping)
{
	if(!element)
	{
		return;
	}
	for(const [name, value] of Object.entries(mapping.attrs || {}))
	{
		if(typeof value === "undefined")
		{
			continue;
		}
		(element as any)[name] = value;
		if(typeof value === "boolean")
		{
			element.toggleAttribute(name, value);
		}
		else if(name === "id" || name === "title")
		{
			element.setAttribute(name, String(value));
		}
	}
}

function applySelectSettings(field : Record<string, any>, attrs : Record<string, any>)
{
	if(typeof field.rows !== "undefined")
	{
		attrs.rows = field.rows;
	}
	if(Number(attrs.rows) > 1)
	{
		attrs.multiple = true;
	}
	const values = field.values || field.select_options || field.options || {};
	if(values && values["@"])
	{
		attrs.searchUrl = values["@"];
	}
	const selectOptions = normalizeCustomfieldOptions(values);
	if(selectOptions.length)
	{
		attrs.select_options = selectOptions;
	}
}

function applyValueSettingsToAttrs(field : Record<string, any>, attrs : Record<string, any>, widgetType : string)
{
	if(!field.values || typeof field.values !== "object" || Array.isArray(field.values))
	{
		return;
	}
	if(["select", "radio", "radiogroup", "checkbox", "button"].includes(String(field.type || widgetType)))
	{
		return;
	}
	for(const [name, value] of Object.entries(field.values))
	{
		if(name === "format" || name === "@")
		{
			continue;
		}
		attrs[name] = value;
	}
}

function mapFilemanagerField(
	_fieldName : string,
	field : Record<string, any>,
	attrs : Record<string, any>
) : Et2CustomfieldWidgetMapping
{
	delete attrs.label;
	const values = field.values && typeof field.values === "object" ? {...field.values} : {};
	if(typeof values.mime !== "undefined" && typeof values.accept === "undefined")
	{
		values.accept = values.mime;
	}
	if(typeof values.max_file_size !== "undefined" && typeof values.maxFileSize === "undefined")
	{
		values.maxFileSize = values.max_file_size;
	}
	for(const name of ["accept", "maxFileSize"])
	{
		if(typeof values[name] !== "undefined")
		{
			attrs[name] = values[name];
		}
	}
	return finalizeMapping("vfs-upload", attrs);
}

function withoutEmptyOption(values : Record<string, any>) : Record<string, any>
{
	const next = {...values};
	delete next[""];
	return next;
}

function finalizeMapping(widgetType : string, attrs : Record<string, any>) : Et2CustomfieldWidgetMapping
{
	if(typeof attrs.needed !== "undefined")
	{
		attrs.required = attrs.needed;
		delete attrs.needed;
	}
	if(typeof attrs.size !== "undefined" && !["small", "medium", "large"].includes(String(attrs.size)))
	{
		const size = Number(attrs.size);
		if(size > 0)
		{
			attrs.width = size + "em";
		}
		delete attrs.size;
	}
	const tagName = resolveWidgetTag(widgetType, attrs.readonly === true);
	return {tagName, attrs};
}

function resolveWidgetTag(widgetType : string, readonly : boolean) : string
{
	const baseTag = widgetType.startsWith("et2-") ? widgetType : "et2-" + widgetType;
	if(readonly && customElements.get(baseTag + "_ro"))
	{
		return baseTag + "_ro";
	}
	if(customElements.get(baseTag))
	{
		return baseTag;
	}
	return "et2-description";
}
