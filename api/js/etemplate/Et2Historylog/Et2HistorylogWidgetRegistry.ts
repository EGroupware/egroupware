/**
 * EGroupware eTemplate2 - history log: which widget renders which field's value
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {et2_registry} from "../et2_core_widget";
import {cleanSelectOptions} from "../Et2Select/FindSelectOptions";
import {mapCustomfieldToWidget} from "../Et2Customfields/Et2CustomfieldWidgetMapper";

/**
 * A resolved "render this value with that widget" instruction.
 *
 * Deliberately data, not a widget instance: the history log has to decide per row which widget a
 * value gets, and building one eagerly for every field an app declares (what the legacy widget
 * did, including one per custom field) cost a full widget tree before the tab was even looked at.
 * A spec is cheap to hold and the cell instantiates from it on first use.
 */
export interface HistorylogRenderSpec
{
	tagName : string;
	attrs : Record<string, any>;
	/**
	 * Sub-specs for a multi-part value, eg. calendar's `participants`, which an app declares as
	 * `['select-account', {...statuses}, {...roles}]` and which renders stacked in one cell.
	 * The keys are the value object's own keys, in declaration order.
	 */
	parts? : { key : string, spec : HistorylogRenderSpec }[];
}

/** Status values the history log understands without an app declaring them */
export const HISTORY_LINK_STATUS = "~link~";
export const HISTORY_FILE_STATUS = "~file~";
export const HISTORY_USER_AGENT_STATUS = "user_agent_action";

/** What the legacy widget used as the custom-field status prefix */
export const HISTORY_CF_PREFIX = "#";

/**
 * Resolve `status-widgets` entries into render specs, once per history log.
 *
 * The app-supplied map is whatever `$content['history']['status-widgets']` held: a widget name
 * ("date-time"), a name with legacy options ("link-entry:infolog"), a select-options object, or an
 * array of any of those for a multi-part value.  Custom fields are not in that map at all - they
 * are resolved from the customfield definitions the server already sent.
 */
export class Et2HistorylogWidgetRegistry
{
	private _specs : Map<string, HistorylogRenderSpec> = new Map();

	/** Labels for the "Changed" column / filter, in the order they should be offered */
	private _labels : { value : string, label : string }[] = [];

	/**
	 * @param statusWidgets the app's `status-widgets` map
	 * @param customfields customfield definitions for the app, keyed by field name (no prefix)
	 * @param lang translation function, for the labels of the built-in statuses
	 */
	constructor(
		statusWidgets : Record<string, any> | null | undefined,
		customfields : Record<string, any> | null | undefined,
		private lang : (s : string) => string = (s) => s
	)
	{
		this._addBuiltins();
		this._addCustomfields(customfields || {});
		this._addApp(statusWidgets || {});
	}

	/**
	 * Render spec for a status, or null to fall back to plain text.
	 */
	get(status : string) : HistorylogRenderSpec | null
	{
		return this._specs.get(status) ?? null;
	}

	has(status : string) : boolean
	{
		return this._specs.has(status);
	}

	/**
	 * Option list for the "Changed" column and, unchanged, for the status filter.
	 *
	 * One list for both on purpose: it means a value the rows genuinely carry (`~file~`, `~link~`,
	 * a custom field) is filterable without inventing a second name for it, and the filter can
	 * never offer something the column cannot display.
	 */
	labels() : { value : string, label : string }[]
	{
		return this._labels.slice();
	}

	/**
	 * Merge in labels the server sent for the status column (`sel_options[<id>][status]`), keeping
	 * any we already have and adding the rest.
	 */
	mergeLabels(options : any)
	{
		cleanSelectOptions(options).forEach((option : any) =>
		{
			const value = String(option.value);
			// An app may send a field with no label at all (infolog maps every custom field to
			// null).  Never let that replace, or stand in for, a real label - falling back to the
			// value would print the raw field code, eg. "#chrgs2" instead of "2-Charges".
			const label = option.label ? String(option.label) : "";
			const existing = this._labels.find(l => l.value == value);
			if(existing)
			{
				// The server's label wins where it has one - it is the app's own field label
				if(label)
				{
					existing.label = label;
				}
			}
			else
			{
				this._labels.push({value: value, label: label || value});
			}
		});
	}

	private _label(value : string, label : string)
	{
		if(!this._labels.find(l => l.value == value))
		{
			this._labels.push({value: value, label: label});
		}
	}

	/**
	 * Statuses every history log has, whatever the app declared.
	 *
	 * `~file~` is what the attachment rows UNIONed in by History::get_rows() carry, and is also
	 * the value the "Changed" filter uses to include or exclude them.
	 */
	private _addBuiltins()
	{
		this._specs.set(HISTORY_LINK_STATUS, {tagName: "et2-link", attrs: {}});
		this._label(HISTORY_LINK_STATUS, this.lang("link"));

		// et2-vfs-path (readonly) replaces the deleted legacy "vfs" widget, and takes either a
		// plain path or a full stat-array - which is what get_rows() puts in new_value for a file.
		this._specs.set(HISTORY_FILE_STATUS, {tagName: "et2-vfs-path", attrs: {}});
		this._label(HISTORY_FILE_STATUS, this.lang("File"));

		// No widget: the value is plain text.  Only a label is needed.
		this._label(HISTORY_USER_AGENT_STATUS, this.lang("User-agent & action"));
	}

	/**
	 * Custom fields, resolved through the shared customfield->widget mapper rather than this
	 * widget's own guesswork, so a history value renders the same way the field itself does.
	 */
	private _addCustomfields(customfields : Record<string, any>)
	{
		for(const name in customfields)
		{
			const field = customfields[name];
			const mapping = mapCustomfieldToWidget(name, field, "", {
				context: "row",
				readonly: true,
				prefix: HISTORY_CF_PREFIX
			});
			if(!mapping)
			{
				continue;
			}
			const status = HISTORY_CF_PREFIX + name;
			// The mapper's id is for a row of that field; here the id is the column, not the field
			const attrs = {...mapping.attrs};
			delete attrs.id;
			delete attrs.value;
			this._specs.set(status, {tagName: mapping.tagName, attrs: attrs});
			this._label(status, field?.label || name);
		}
	}

	private _addApp(statusWidgets : Record<string, any>)
	{
		for(const status in statusWidgets)
		{
			const spec = this._resolve(statusWidgets[status]);
			if(spec)
			{
				this._specs.set(status, spec);
			}
			// No label added here: the server sends the app's own field labels for the status
			// column (see mergeLabels()), which are better than the raw field code we have.
		}
	}

	/**
	 * Turn one `status-widgets` entry into a spec.
	 */
	private _resolve(field : any) : HistorylogRenderSpec | null
	{
		if(field === null || typeof field === "undefined" || field === "")
		{
			return null;
		}
		// An app may wrap a single entry in an array
		if(Array.isArray(field) && field.length === 1)
		{
			field = field[0];
		}
		if(typeof field === "object")
		{
			return this._resolveObject(field);
		}
		return this._resolveName(String(field));
	}

	/**
	 * An object entry is either a multi-part value (each part a widget name or options object) or
	 * a plain select-options map for a single select.
	 */
	private _resolveObject(field : Record<string, any>) : HistorylogRenderSpec | null
	{
		// Multi-part if any part names a real widget, or is itself an options object.  A bare
		// value->label map (the common case) is select options for one widget instead.
		// 'template' is excluded deliberately: it is both a widget name and an InfoLog todo
		// status, and the latter is what an app means here.
		const parts = Object.keys(field).filter(key =>
		{
			const part = field[key];
			if(typeof part === "object" && part !== null)
			{
				return typeof part.value === "undefined";
			}
			return (et2_registry[part] && part !== "template") || !!customElements.get(String(part));
		});
		if(!parts.length)
		{
			return {tagName: "et2-select", attrs: {select_options: cleanSelectOptions(field)}};
		}
		return {
			tagName: "et2-vbox",
			attrs: {},
			parts: Object.keys(field).map(key =>
			{
				const part = field[key];
				const spec = typeof part === "object" && part !== null && typeof part.value === "undefined"
							 ? {tagName: "et2-select", attrs: {select_options: cleanSelectOptions(part)}}
							 : this._resolveName(String(part));
				return {key: key, spec: spec ?? {tagName: "et2-description", attrs: {}}};
			})
		};
	}

	/**
	 * Resolve a widget name, which may carry legacy options after a colon (eg. "link-entry:infolog").
	 */
	private _resolveName(name : string) : HistorylogRenderSpec | null
	{
		let legacyOptions : string[] = [];
		if(name.indexOf(":") > 0)
		{
			legacyOptions = name.split(":");
			name = legacyOptions.shift() as string;
		}
		// Prefer a web component, readonly variant first - same order the legacy widget tried
		const tries = [name, "et2-" + name + "_ro", "et2-" + name];
		const tagName = tries.find(tag => !!customElements.get(tag));
		if(!tagName)
		{
			// Not a web component (an app naming a widget that has not been converted, or a typo).
			// The cell falls back to plain text and warns once; see Et2HistorylogValue.
			return null;
		}
		const attrs : Record<string, any> = {};
		// Map the colon-separated legacy options onto the target's declared legacyOptions
		const declared = (customElements.get(tagName) as any)?.legacyOptions || [];
		for(let i = 0; i < legacyOptions.length && i < declared.length; i++)
		{
			if(legacyOptions[i] !== "")
			{
				attrs[declared[i]] = legacyOptions[i];
			}
		}
		return {tagName: tagName, attrs: attrs};
	}
}
