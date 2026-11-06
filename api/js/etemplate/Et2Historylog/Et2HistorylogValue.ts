/**
 * EGroupware eTemplate2 - history log: one changed value, rendered by the right widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {css, html, LitElement, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import type {Et2Historylog} from "./Et2Historylog";
import type {HistorylogRenderSpec} from "./Et2HistorylogWidgetRegistry";
import {adoptDiffStyles} from "./Et2Historylog.diff.styles";

/** What the server puts in old_value when it stored a unified diff instead of both values */
export const HISTORY_DIFF_MARKER = "***diff***";

/** Separator the server uses to join the parts of a 1:N value (Api\Storage\Tracking) */
export const HISTORY_ONE2N_SEPARATOR = "~|~";

/**
 * One cell of the history log's "New value" / "Old value" columns.
 *
 * Exists because the datagrid renders a *fixed* row template, while the history log needs a
 * different widget per row: which one depends on that row's `status`, and an app can map any of
 * its fields to any widget.  Rather than teaching the shared row renderer to swap tags per row,
 * the tag in the row template is fixed (this element) and the variation lives inside it.
 *
 * Bound with `id="$row"`, so it receives the whole row rather than one field, and reads both the
 * field it renders and the `status` that decides how from it.
 *
 * @csspart value - wrapper around the resolved widget
 */
@customElement("et2-historylog-value")
export class Et2HistorylogValue extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			...(super.styles || []),
			css`
				:host {
					display: block;
					min-width: 0;
					overflow: hidden;
					text-overflow: ellipsis;
				}

				/* A diff spans both value columns - see Et2Historylog's row customizer for the
				   cell-level part of this.  et2-diff caps its own height and offers a pop-out. */
				:host([diff]) {
					overflow: visible;
				}

				.value {
					min-width: 0;
				}

				/* Multi-part values (eg. calendar participants) stack, one line per part */
				.parts {
					display: flex;
					flex-direction: column;
					gap: var(--sl-spacing-3x-small);
				}
			`
		];
	}

	/** Which of the row's two value fields this cell shows */
	@property({type: String})
	field : "new_value" | "old_value" = "new_value";

	/**
	 * This cell is the one rendering the diff, and its cell spans both value columns.
	 */
	@property({type: Boolean, reflect: true})
	diff = false;

	/**
	 * This cell belongs to a row that is showing a diff - true for *both* value cells, so the
	 * paired old-value cell can take itself out of the grid flow and let the diff have both
	 * tracks.  Reflected because the span is done in CSS, from the datagrid's row stylesheet.
	 */
	@property({type: Boolean, reflect: true, attribute: "diff-row"})
	diffRow = false;

	private _row : any = {};

	/** The widget currently rendering the value, kept so it can be reused across row re-binds */
	private _widget : any = null;
	private _widgetTag : string = "";
	private _partWidgets : { key : string, widget : any }[] = [];

	/**
	 * Warnings already emitted, so a status with no usable widget complains once rather than
	 * once per rendered row.
	 */
	private static _warned : Set<string> = new Set();

	/**
	 * The whole row, via `id="$row"` in the row template.
	 */
	set value(row : any)
	{
		this._row = row && typeof row === "object" ? row : {};
		this.requestUpdate("value");
	}

	get value() : any
	{
		return this._row;
	}

	/**
	 * A display widget - it must never contribute to a submit, and is never dirty.
	 */
	isDirty() : boolean
	{
		return false;
	}

	getValue() : null
	{
		return null;
	}

	/**
	 * Find the history log this cell belongs to.
	 *
	 * Row widgets have no widget-tree parent (the datagrid builds rows from a cloned template
	 * rather than as child widgets), and the rows live in the datagrid's shadow DOM, so neither
	 * getParent() nor a plain closest() reaches the owner.  Hop shadow boundaries instead.
	 */
	private _owner() : Et2Historylog | null
	{
		let element : Element | null = this;
		while(element)
		{
			const found = element.closest("et2-historylog");
			if(found)
			{
				return <Et2Historylog><unknown>found;
			}
			const root = element.getRootNode();
			element = root instanceof ShadowRoot ? root.host : null;
		}
		return null;
	}

	/**
	 * Does this row's value need the diff widget?
	 *
	 * The server replaces both values with a unified diff plus a marker when a value is long or
	 * multi-line, so this is a property of the row, not of the field - both columns have to agree,
	 * or one would render a diff while the other showed the marker as text.
	 */
	private _isDiffRow() : boolean
	{
		return this._row?.old_value === HISTORY_DIFF_MARKER;
	}

	private _fieldValue() : any
	{
		let value = this._row?.[this.field];
		// A 1:N value arrives joined; the server explodes it for us, but a row that came from the
		// cache (or an app hook) may still carry the raw form.
		if(typeof value === "string" && value.indexOf(HISTORY_ONE2N_SEPARATOR) !== -1)
		{
			value = value.split(HISTORY_ONE2N_SEPARATOR);
		}
		return value ?? "";
	}

	willUpdate(changed : Map<string, unknown>)
	{
		super.willUpdate?.(changed);
		this.diffRow = this._isDiffRow();
		this.diff = this.diffRow && this.field === "new_value";
	}

	firstUpdated(changed : Map<string, unknown>)
	{
		super.firstUpdated?.(changed);
		// Capture, so it runs before the click reaches et2-diff's own open-the-dialog handler
		this.shadowRoot?.querySelector(".value")?.addEventListener("click", this._handleDiffClick, true);
	}

	/**
	 * Pop a diff out into a dialog, but let the history log host it rather than et2-diff.
	 *
	 * et2-diff's own pop-out is a dialog it renders next to the diff, positioned `fixed`.  A fixed
	 * element is placed against the nearest ancestor that establishes a containing block rather
	 * than against the viewport, and the datagrid's virtualizer gives every row a transform and
	 * its body `contain: layout` - both of which establish one.  So a dialog opened from inside a
	 * row is sized to that row: the panel collapses to a couple of pixels high and its title, the
	 * diff and the OK button spill out across the grid.  The history log's own shadow root sits
	 * outside the virtualizer, where the same dialog lays out normally.
	 */
	private _handleDiffClick = (event : MouseEvent) =>
	{
		if(!this.diff || !this._widget?.overflowing)
		{
			// Nothing hidden, nothing to pop out.  Left un-stopped so et2-diff sees the click and
			// makes the same decision, rather than this quietly swallowing it.
			return;
		}
		const owner = this._owner();
		if(typeof owner?.showDiff !== "function")
		{
			// Nobody to host it - leave et2-diff to its own devices rather than swallowing the click
			return;
		}
		event.stopPropagation();
		event.preventDefault();
		owner.showDiff(this._fieldValue());
	};

	updated(changed : Map<string, unknown>)
	{
		super.updated?.(changed);
		this._syncWidget();
	}

	/**
	 * Build (or re-use) the widget for this row and give it the value.
	 *
	 * Re-use matters: the datagrid recycles row elements while scrolling, so a cell is re-bound far
	 * more often than it changes widget type.  Only a status change that resolves to a different
	 * tag rebuilds.
	 */
	private _syncWidget()
	{
		const container = this.shadowRoot?.querySelector(".value");
		if(!container)
		{
			return;
		}
		const spec = this._resolveSpec();
		const tag = spec?.tagName || "";
		if(tag !== this._widgetTag)
		{
			container.replaceChildren();
			this._widget = null;
			this._partWidgets = [];
			this._widgetTag = tag;
			if(spec)
			{
				this._build(spec, container, this._row?.status);
			}
		}
		this._apply(spec);
	}

	/**
	 * Which widget this row's value gets, or null for plain text.
	 */
	private _resolveSpec() : HistorylogRenderSpec | null
	{
		// The old-value column of a diff row holds only the marker - the diff itself is rendered
		// once, by the new-value column, spanning both.
		if(this._isDiffRow())
		{
			return this.field === "new_value" ? {tagName: "et2-diff", attrs: {}} : null;
		}
		const status = this._row?.status;
		if(!status)
		{
			return null;
		}
		const owner = this._owner();
		const spec = owner?.widgetRegistry?.get(status) ?? null;
		if(!spec && owner?.widgetRegistry && !Et2HistorylogValue._warned.has(status))
		{
			// Only complain for a status the app actually declared - most statuses legitimately
			// have no widget and render as text.
			if(owner.statusWidgets && typeof owner.statusWidgets[status] !== "undefined")
			{
				Et2HistorylogValue._warned.add(status);
				this.egw()?.debug("warn", "Et2Historylog: no usable widget for status '" + status +
					"' - showing its value as text.  Check the app's status-widgets map.", owner.statusWidgets[status]);
			}
		}
		return spec;
	}

	/**
	 * Instantiate one display widget, or null if it cannot be built.
	 *
	 * loadWebComponent() throws for a tag that is not registered, and this runs inside the
	 * datagrid's row-hydration loop - one unregistered tag (an app naming a widget from a
	 * not-yet-loaded bundle, a typo in status-widgets) would otherwise take the whole batch of
	 * rows down rather than degrading that one cell to text.
	 */
	private _create(tagName : string, attrs : Record<string, any>, owner : Et2Historylog | null) : any
	{
		if(!tagName || !customElements.get(tagName))
		{
			return null;
		}
		try
		{
			return loadWebComponent(tagName, attrs, <any>owner ?? undefined);
		}
		catch(e)
		{
			this.egw()?.debug("warn", "Et2Historylog: could not create '" + tagName + "' for a history value", e);
			return null;
		}
	}

	/**
	 * @param status the row's status, used as the widget's id - see below
	 */
	private _build(spec : HistorylogRenderSpec, container : Element, status? : string)
	{
		const owner = this._owner();
		// The id matters: an Et2Select resolves its options through the array manager by id, and
		// the history log's sel_options are namespaced under its own widget id with one entry per
		// field (HistoryLog::beforeSendToClient()).  Given the status as its id a select finds
		// exactly that field's options; left without one it resolves to the namespace *root* and
		// takes the whole `{status: ..., col_filter: ..., Ty: ..., St: ...}` object as its option
		// list, so nothing matches and the cell renders blank.  The legacy widget passed the
		// field code as the id for the same reason.
		if(spec.tagName === "et2-diff")
		{
			// The rules that colour a diff live in the page's theme stylesheet, which does not
			// reach into this shadow root - see Et2Historylog.diff.styles.
			adoptDiffStyles(this.shadowRoot);
		}
		const attrs = {readonly: true, ...(status ? {id: status} : {}), ...spec.attrs};
		const widget = this._create(spec.tagName, attrs, owner);
		if(!widget)
		{
			// Fall back to text rather than leaving the cell empty.  _widgetTag is already set to
			// the tag we failed to build, so reset it or the next re-bind would think the widget
			// is still there and skip rebuilding.
			this._widgetTag = "";
			return;
		}
		this._widget = widget;
		if(spec.parts?.length)
		{
			widget.classList.add("parts");
			spec.parts.forEach(part =>
			{
				// Same reasoning as above, per part: a multi-part value's sub-options live under
				// the part's own key (eg. calendar's participants status/role lists).
				const child = this._create(part.spec.tagName, {readonly: true, id: part.key, ...part.spec.attrs}, owner);
				if(child)
				{
					widget.appendChild(child);
					this._partWidgets.push({key: part.key, widget: child});
				}
			});
		}
		container.appendChild(widget);
	}

	private _apply(spec : HistorylogRenderSpec | null)
	{
		const container = this.shadowRoot?.querySelector(".value");
		const value = this._fieldValue();
		if(!spec || !this._widget)
		{
			if(!container)
			{
				return;
			}
			// The old-value cell of a diff row holds only the server's marker, and the diff itself
			// is rendered by the new-value cell spanning both columns.  Show nothing: the marker is
			// an internal sentinel, and the CSS that hides this cell is a layout nicety, not the
			// thing that must stop "***diff***" reaching the user.
			if(this.diffRow)
			{
				container.textContent = "";
				return;
			}
			// Plain text fallback.  textContent, not a template: the value is app data.
			container.textContent = value === null || typeof value === "object" ? "" : String(value);
			return;
		}
		if(this._partWidgets.length)
		{
			this._partWidgets.forEach(part =>
			{
				this._assign(part.widget, value && typeof value === "object" ? (value[part.key] ?? "") : "");
			});
			return;
		}
		this._assign(this._widget, value);
	}

	/**
	 * Hand a value to a display widget through whichever setter it offers.
	 *
	 * `set_value()`/`setValue()` before the plain `value` property, because some widgets accept a
	 * richer shape there than their property does and history rows carry those shapes: a `~file~`
	 * row's value is the server's stat object, and `Et2VfsPath.value` is typed string-only - it
	 * runs the object through decodePath() and renders "[object Object]", while its setValue()
	 * pulls `.path` out first.  Found live on infolog's attachment rows.
	 */
	private _assign(widget : any, value : any)
	{
		if(typeof widget.set_value === "function")
		{
			widget.set_value(value);
		}
		else if(typeof widget.setValue === "function")
		{
			widget.setValue(value);
		}
		else
		{
			widget.value = value;
		}
	}

	render()
	{
		return html`
            <div part="value" class="value"></div>
            ${nothing}
		`;
	}
}
