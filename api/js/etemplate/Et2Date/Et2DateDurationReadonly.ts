/**
 * EGroupware eTemplate2 - Readonly duration WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "lit";
import {Et2DateDuration, formatOptions} from "./Et2DateDuration";
import {dateStyles} from "./DateStyles";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";


/**
 * This is a stripped-down read-only widget used in nextmatch
 *
 * @slot prefix - Used to prepend a presentational icon or similar element to the widget.
 * @slot suffix - Like prefix, but after
 */
@customElement("et2-date-duration_ro")
export class Et2DateDurationReadonly extends Et2DateDuration
{
	static get styles()
	{
		return [
			...super.styles,
			...dateStyles,
			css`
			:host {
			border: none;
			min-width: 2em;
				padding-right:var(--sl-spacing-small)
			}
			`
		];
	}

	/**
	 * Spell the duration out in words, with the unit written in the user's language
	 *
	 * Empty (the default) keeps the compact display.  Otherwise the value is one of the Intl.NumberFormat
	 * unitDisplay styles: "long" gives "3 days", "short" "3 days" or "3 hr" and "narrow" "3d" or "3h",
	 * as far as the language has those forms.  The best unit (days of hoursPerDay, hours or minutes) is picked
	 * like the editable widget does.  An empty duration shows nothing at all, not even the label.
	 */
	@property({type: String, reflect: true})
	unitDisplay : "" | "long" | "short" | "narrow" = "";

	constructor()
	{
		super();

		// Property defaults
		this.selectUnit = false;	// otherwise just best matching unit will be used for eg. display_format "h:m:s"
	}

	get value()
	{
		return this.__value;
	}

	set value(_value)
	{
		const old_value = this.__value;
		this.__value = _value;
		this.requestUpdate("value", old_value);
	}

	get innerText() : string
	{
		return this.shadowRoot.querySelector('span').innerText;
	}

	render()
	{
		let parsed = this.__value;

		const format_options = <formatOptions>{
			selectUnit: this.selectUnit,
			displayFormat: this.displayFormat,
			dataFormat: this.dataFormat,
			numberFormat: this.egw().preference("number_format"),
			hoursPerDay: this.hoursPerDay,
			emptyNot0: this.emptyNot0
		};

		if(this.unitDisplay)
		{
			return this._renderWords(parsed, format_options);
		}

		const display = this.formatter(parsed, format_options);
		return html`
            <slot name="prefix"></slot>
            <span ${this.id ? html`id="${this._dom_id}"` : ''}>
                  ${display.value}${display.unit}
            </span>
            <slot name="suffix"></slot>
		`;
	}

	/**
	 * Language to write the units in: the user's preference, not the browser's.
	 *
	 * The preference can be unavailable early on, then the language the server rendered the page in is used.
	 */
	protected get unitLocale() : string
	{
		const pref = this.egw().preference("lang", "common");
		return (typeof pref === "string" && pref) || document.documentElement.lang || "en";
	}

	protected _renderWords(parsed, format_options : formatOptions)
	{
		// Best unit, with "." as decimal separator so it can be read back as a number
		const best = this.formatter(parsed, {...format_options, selectUnit: true, number_format: ".,"});
		const unit = {d: "day", h: "hour", m: "minute"}[best.unit];
		if(!unit || best.value === "")
		{
			return nothing;
		}
		let text : string;
		try
		{
			text = new Intl.NumberFormat(this.unitLocale, {
				style: "unit", unit: unit, unitDisplay: this.unitDisplay, maximumFractionDigits: 2
			}).format(parseFloat(best.value));
		}
		catch(e)
		{
			// Language tag the browser does not know, give the number with the plain unit
			text = best.value + " " + best.unit;
		}
		return html`
            ${this.label ? html`<span class="form-control-label" part="form-control-label">${this.label}</span>` : nothing}
            <span ${this.id ? html`id="${this._dom_id}"` : ''}>${text}</span>
		`;
	}

	/**
	 * These are the attributes we allow to change for each row
	 *
	 * @param attrs
	 */
	getDetachedAttributes(attrs)
	{
		attrs.push("id", "value", "class", "disabled");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data? : any) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}