import type {Et2CustomfieldDefinition} from "./Et2CustomfieldsController";

export type Et2CustomfieldRenderContext = "field" | "list" | "filters" | "row";

export interface Et2CustomfieldWidgetMappingOptions
{
	context : Et2CustomfieldRenderContext;
	readonly? : boolean;
	apps? : Record<string, any>;
	prefix? : string;
	/**
	 * Handler to put on the generated widget.
	 *
	 * A customfields widget's `onchange` belongs to the field it renders, not to the container:
	 * templates written against the legacy widget expect `widget` in the handler to be the
	 * generated input, so they can read its value and options.
	 */
	onchange? : string | Function;
	/** Label to use instead of the customfield's own, for a widget placed as a single field. */
	label? : string;
}

export interface Et2CustomfieldWidgetMapping
{
	tagName : string;
	attrs : Record<string, any>;
}

/**
 * Container an editable filemanager customfield lists its files in.
 *
 * Shared so the widget that renders it and the mapping that points at it cannot drift apart.
 */
export const CUSTOMFIELD_FILE_LIST_CLASS = "customfields__file-list";

export function mapCustomfieldToWidget(
	fieldName : string,
	field : Et2CustomfieldDefinition & Record<string, any>,
	value : any,
	options : Et2CustomfieldWidgetMappingOptions
) : Et2CustomfieldWidgetMapping | null
{
	const context = options.context || "list";
	const prefix = options.prefix || "#";
	const apps = options.apps || defaultLinkApps();
	const attrs : Record<string, any> = {
		id: prefix + fieldName,
		noLang: true,
		readonly: options.readonly === true,
		statustext: field?.help || "",
		value: value ?? ""
	};
	// Who shows the field's name depends on the context:
	//  - "field" and "filters" build a real form control, and a custom element is not a
	//    labelable element, so a sibling <label for="#name"> would be inert for both screen
	//    readers and click-to-focus.  The generated widget carries its own label instead, which
	//    also gets it the placement and readonly handling every other Et2 form control has.
	//  - "list" and "row" show values only; the name goes on the surrounding row as title /
	//    data-label so a list column is not prefixed with its own heading on every line.
	if(context === "field" || context === "filters")
	{
		attrs.label = options.label || field?.label || fieldName;
	}
	if(typeof options.onchange !== "undefined" && options.onchange !== null && options.onchange !== "")
	{
		attrs.onchange = options.onchange;
	}
	if(context === "field")
	{
		// Line the labels up into a column, the way the table this replaced did.  et2-label-fixed is
		// the shared way to ask for that - it gives form-control-label a width of --label-width -
		// so a customfield ends up looking like every other labelled control on the dialog.
		attrs.class = [attrs.class, "et2-label-fixed"].filter(Boolean).join(" ");
	}
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
		if(sourceType === "filemanager")
		{
			return mapFilemanagerField(fieldName, field, attrs, context === "field" && !attrs.readonly);
		}
		const app = typeof field.only_app === "undefined"
			? sourceType
			: (field.onlyApp ?? field.only_app);
		attrs.value = normalizeLinkValue(app, value);
		if(attrs.readonly && context !== "filters")
		{
			widgetType = "link";
			attrs.app = app;
		}
		else
		{
			widgetType = "link-entry";
			attrs.onlyApp = app;
			attrs.searchOptions = {filter: field.values || {}};
		}
		return finalizeMapping(widgetType, attrs);
	}

	switch(sourceType)
	{
		case "text":
			widgetType = Number(field.rows) > 1 ? "textarea" : "textbox";
			attrs.noAiTools = true;
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
			// et2-password, not a textbox with type=password: the customfield is meant to get the
			// reveal button and generator that widget provides.  Where it is only being shown,
			// resolveWidgetTag() picks et2-password_ro instead, as it does for every other type.
			widgetType = "password";
			Object.assign(attrs, {
				viewable: field.values?.viewable ?? true,
				plaintext: field.values?.plaintext ?? false,
				suggest: field.values?.suggest ?? 16,
				autocomplete: field.values?.autocomplete ?? "new-password"
			});
			break;

		case "serial":
			widgetType = "textbox";
			attrs.readonly = true;
			break;

		case "int":
			widgetType = "number";
			attrs.precision = 0;
			break;

		case "float":
			widgetType = "number";
			if(field.len)
			{
				attrs.size = field.len;
			}
			break;

		case "select":
			applySelectSettings(field, attrs);
			break;

		case "select-account":
			attrs.empty_label = "Select";
			if(field.account_type)
			{
				attrs.account_type = field.account_type;
			}
			applySelectSettings(field, attrs);
			break;

		case "date":
			attrs.data_format = field.values?.format || "Y-m-d";
			break;

		case "date-time":
			attrs.data_format = field.values?.format || "Y-m-d H:i:s";
			break;

		case "htmlarea":
			attrs.noAiTools = true;
			attrs.config = {
				...(field.config || {}),
				toolbarStartupExpanded: false
			};
			if(field.len)
			{
				// TinyMCE's own init config wants a plain pixel length, not a CSS one
				attrs.config.width = field.len + "px";
			}
			attrs.config.height = ((Number(field.rows) > 0 ? Number(field.rows) : 5) * 16) + "px";
			// The host also needs a floor of its own - see the et2-htmlarea rule in the stylesheet
			break;

		case "radio":
			widgetType = "select";
			attrs.select_options = normalizeCustomfieldOptions(withoutEmptyOption(field.values || {}));
			if(field.values && field.values[""])
			{
				// The "" entry is the caption of the empty option, not the field's name
				attrs.emptyLabel = field.values[""];
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
			// A button in a list would be a detached row's problem, and there is nothing to press
			// in a readonly one
			if(context !== "field" || attrs.readonly)
			{
				return null;
			}
			applyButtonSettings(field, attrs);
			break;

		case "filemanager":
			return context === "filters" ? null : mapFilemanagerField(fieldName, field, attrs, context === "field" && !attrs.readonly);

		case "label":
		case "header":
			// Not an input: these put a caption in the middle of the form, showing the field's own
			// label, and have nothing to submit.  Readonly keeps them out of the submitted values.
			widgetType = "label";
			attrs.value = attrs.label || field.label || fieldName;
			attrs.readonly = true;
			delete attrs.label;
			delete attrs.needed;
			// No id: a caption has no value, and an id here would be a second widget answering to
			// a customfield's name
			delete attrs.id;
			attrs.class = [attrs.class, "et2_customfield_" + sourceType]
				.filter(Boolean).join(" ").replace("et2-label-fixed", "").trim();
			break;

		case "url":
			if(context === "list" || context === "row")
			{
				// A bare address says nothing in a list, so show the field's name instead
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

function defaultLinkApps() : Record<string, any>
{
	try
	{
		const egw = (globalThis as any).egw;
		const egwInstance = typeof egw === "function" ? egw() : egw;
		return egwInstance?.link_app_list?.() || {};
	}
	catch(e)
	{
		return {};
	}
}

function normalizeLinkValue(app : string, value : any)
{
	if(!value)
	{
		return "";
	}
	if(typeof value === "object")
	{
		return {
			...value,
			app: value.app || app,
			id: value.id ?? value.entryId ?? value.value ?? ""
		};
	}
	return {
		app,
		id: String(value)
	};
}

export function applyCustomfieldWidgetMapping(element : Element | undefined, mapping : Et2CustomfieldWidgetMapping)
{
	if(!element)
	{
		return;
	}
	const attrs = {...(mapping.attrs || {})};
	if(typeof (element as any).transformAttributes === "function")
	{
		(element as any).transformAttributes(attrs);
	}
	for(const [name, value] of Object.entries(attrs))
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

/**
 * The AI assistant is off for customfields, matching what the server sets for these types
 * (`Customfields::_widget()`), and a customfield turns it on with `values.noAiTools = false` like
 * any other setting.  Regular template widgets get wrapped by the preprocessor in api/etemplate.php
 * instead, which reads the same attribute off the tag - these never pass through it, being built
 * here rather than written in a template.
 */

/**
 * Hand a customfield's `values` to the generated widget as properties.
 *
 * `values` holds the options for the types that have them, but for every other type it is a bag of
 * settings for whatever widget the field renders as - which is how a customfield configures
 * anything the mapper does not know about, eg. an htmlarea's `mode`, or a date's `min`/`max`.
 * `format` and `@` are consumed here instead: they configure the mapping, not the widget.
 *
 * Nothing filters these against the widget's declared properties.  The legacy widget looked like it
 * did, via `getPropertyOptions()`, but Lit returns a default declaration for a name it does not
 * know, so that test passed for anything and every entry was set - keep it that way, or a
 * customfield that has always configured its widget this way would quietly stop working.
 */
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
	attrs : Record<string, any>,
	editable : boolean = false
) : Et2CustomfieldWidgetMapping
{
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
	// A compact one-line entry rather than a tall thumbnail row, which is what the legacy widget
	// gave this upload whether or not it was readonly.  Et2File sets the same two itself, but only
	// in loadFromXML(), which never runs for a customfield: its widgets are built from these
	// attributes rather than from a template node.
	Object.assign(attrs, {
		display: "small",
		inline: true
	});
	// The upload renders its label inside its own button, which leaves the field's name in a
	// different place from every other customfield's - and gone entirely whenever that button is
	// not there, which is both what noUpload does and what readonly does.  Et2Customfields puts it
	// in the label column beside the controls instead.
	delete attrs.label;
	if(editable)
	{
		// The legacy widget built this upload by hand; these are the attributes it gave it.
		Object.assign(attrs, {
			required: attrs.required ?? attrs.needed,
			helptext: "fileupload"
		});
		delete attrs.needed;
		// `values.noUpload` hides the upload button, `values.noVfsSelect` the "link existing file"
		// one - both are read off the widget as classes, the way the legacy widget set them.
		attrs.class = [attrs.class, "et2_file", values.noUpload ? "noUpload" : "", values.noVfsSelect ? "noVfsSelect" : ""]
			.filter(Boolean).join(" ");
		// An upload lists its files between its own button and whatever comes next, which here is
		// the button for linking a file already in the VFS - so a field holding a file put that
		// file between the two buttons.  Et2Customfields renders a container after both and this
		// moves the list into it.  Only where that container exists: a readonly field has no
		// second button to be separated from, and a list row renders no container at all.
		attrs.fileListTarget = "." + CUSTOMFIELD_FILE_LIST_CLASS;
	}
	return finalizeMapping("vfs-upload", attrs);
}

/**
 * A `button` customfield's values are `label=onclick` pairs.
 *
 * With one pair the button takes that label and handler.  With none there is nothing to press, so
 * say so rather than render a button that does nothing.
 */
function applyButtonSettings(field : Record<string, any>, attrs : Record<string, any>)
{
	attrs.label = field.label;
	const values = field.values && typeof field.values === "object" ? field.values : {};
	const keys = Object.keys(values);
	if(keys.length)
	{
		attrs.label = keys[0];
		attrs.onclick = values[keys[0]];
		return;
	}
	attrs.label = 'No "label=onclick" in values!';
	attrs.onclick = () => false;
}

/**
 * Map one customfield to every widget it renders as.
 *
 * Almost always exactly one.  A `button` customfield carrying several `label=onclick` values is the
 * exception: it renders one button per value, each with its own id, so they do not collide.
 *
 * @param {string} fieldName Unprefixed customfield name.
 * @param {Object} field Its definition.
 * @param {*} value Its current value.
 * @param {Et2CustomfieldWidgetMappingOptions} options Render context and overrides.
 * @return {Et2CustomfieldWidgetMapping[]} Empty when the field renders nothing here.
 */
export function mapCustomfieldToWidgets(
	fieldName : string,
	field : Et2CustomfieldDefinition & Record<string, any>,
	value : any,
	options : Et2CustomfieldWidgetMappingOptions
) : Et2CustomfieldWidgetMapping[]
{
	const mapping = mapCustomfieldToWidget(fieldName, field, value, options);
	if(!mapping)
	{
		return [];
	}
	const values = field?.values && typeof field.values === "object" ? field.values : {};

	if(String(field?.type || "").replace(/_/g, "-") === "filemanager" && !mapping.attrs.readonly && !values.noVfsSelect)
	{
		return [mapping, vfsSelectMapping(mapping)];
	}
	if(String(field?.type) !== "button" || Object.keys(values).length < 2)
	{
		return [mapping];
	}
	return Object.keys(values).map((key) => ({
		tagName: mapping.tagName,
		attrs: {...mapping.attrs, id: mapping.attrs.id + "_" + key, label: key, onclick: values[key]}
	}));
}

/**
 * The button beside a filemanager customfield's upload, for linking a file already in the VFS.
 *
 * Uploading is only half of what such a customfield is for - a field configured with `noUpload` has
 * nothing but this button - so it is offered unless the customfield turns it off with
 * `values.noVfsSelect`.  The upload widget it belongs to supplies the path to link into; the two are
 * tied together by the change handler Et2Customfields puts on it.
 *
 * @param {Et2CustomfieldWidgetMapping} upload The upload this button belongs to.
 */
function vfsSelectMapping(upload : Et2CustomfieldWidgetMapping) : Et2CustomfieldWidgetMapping
{
	const attrs = {...upload.attrs};
	delete attrs.needed;
	delete attrs.value;
	return {
		tagName: "et2-vfs-select",
		attrs: {
			...attrs,
			id: upload.attrs.id + "_vfs_select",
			// "~" is the user's own home, where the dialog opens
			path: "~",
			method: "EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_existing",
			methodId: upload.attrs.path,
			buttonLabel: "Link",
			class: "et2_vfs_btn",
			statustext: "select file(s) from vfs",
			required: upload.attrs.required ?? upload.attrs.needed
		}
	};
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
