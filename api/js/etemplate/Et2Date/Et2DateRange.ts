import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {css, html, LitElement, TemplateResult} from "lit";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {ifDefined} from "lit/directives/if-defined.js";
import shoelace from "../Styles/shoelace";
import {dateStyles} from "./DateStyles";
import {formatDate, parseDate} from "./Et2Date";
import {egw} from "../../jsapi/egw_global";

/**
 * Display a time duration (eg: 3 days, 6 hours)
 *
 * If not specified, the time is in assumed to be minutes and will be displayed with a calculated unit
 * but this can be specified with the properties.
 */
export class Et2DateRange extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			...dateStyles,
			css`

				.form-control-input {
					display: flex;
					flex-direction: row;
					flex-wrap: nowrap;
					align-items: baseline;
				}
			`,
		];
	}

	/**
	 * Is the date range relative (this week) or absolute (2016-02-15 - 2016-02-21).  This will affect the value returned.
	 */
	@property({type: Boolean})
	relative : boolean;

	/**
	 * Value set while not connected, applied once the element connects.
	 * The setter must not wait on updateComplete while disconnected: on an element
	 * that never (re-)connects updateComplete is already resolved, so the retry
	 * re-enters the setter in an endless microtask loop and freezes the tab.
	 */
	private _disconnectedValue : { to : string, from : string } | string;

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
		if(typeof this._disconnectedValue !== "undefined")
		{
			const value = this._disconnectedValue;
			this._disconnectedValue = undefined;
			this.updateComplete.then(() =>
			{
				this.value = value;
			});
		}
	}

	_handleChange(event)
	{
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		});
	}

	render()
	{
		const hasLabel = this.label ? true : false
		const hasHelpText = this.helpText ? true : false;

		return html`
            <div part="form-control" class=${classMap({
                'form-control': true,
                'form-control--has-label': this.label.split("%")[0] || false
            })}>
                <div class="form-control__label" part="form-control-label">
                    <label
                            part="form-control-label"
                            class="form-control__label"
                            for="input"
                            aria-hidden=${hasLabel ? 'false' : 'true'}
                    >
                        <slot name="label">${this.label}</slot>
                    </label>
                </div>
                <div class="form-control-input" part="form-control-input"
                >
                    ${this._inputGroupTemplate()}
                </div>
				<slot
						name="help-text"
						part="form-control-help-text"
						id="help-text"
						class="form-control__help-text"
						aria-hidden=${hasHelpText ? 'false' : 'true'}
				>
					${this.helpText}
				</slot>
            </div>
		`;
	}

	protected _inputGroupTemplate() : TemplateResult
	{
		return html`
		<slot name="prefix" part="prefix" class="input__prefix"></slot>
		${this.relative ? this._inputRelativeTemplate() : this._inputAbsoluteTemplate()}
		<slot name="suffix" part="suffix" class="input__suffix"></slot>
		`;
	}

	/**
	 * We're doing a relative date range, show the relative options
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _inputRelativeTemplate() : TemplateResult
	{
		return html`
            <et2-select
                    name="relative"
                    ?disabled=${this.disabled}
                    ?readonly=${this.readonly}
                    ?required=${this.required}
                    placeholder=${ifDefined(this.placeholder)}
                    .emptyLabel=${ifDefined(this.emptyLabel)}
                    .select_options=${Et2DateRange.relative_dates}
                    @change=${this._handleChange}
            ></et2-select>`;
	}

	/**
	 * We're doing an absolute date range, we need start and end dates
	 *
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _inputAbsoluteTemplate() : TemplateResult
	{
		return html`
			<et2-date
				name="from"
				?disabled=${this.disabled}
				?readonly=${this.readonly}
				?required=${this.required}
                placeholder=${ifDefined(this.placeholder || this.egw().lang("From"))}
				defaultDate=${ifDefined(this.value?.from)}
                @change=${this._handleChange}
            ></et2-date>
            <et2-date
                    name="to"
                    ?disabled=${this.disabled}
				?readonly=${this.readonly}
				?required=${this.required}
                    placeholder=${ifDefined(this.placeholder || this.egw().lang("To"))}
				value=${ifDefined(this.value?.to)}
                    @change=${this._handleChange}
            ></et2-date>
		`;
	}

	public get fromElement() : HTMLElement
	{
		return this.shadowRoot?.querySelector("[name='from']");
	}
	public get toElement() : HTMLElement
	{
		return this.shadowRoot?.querySelector("[name='to']");
	}
	public get relativeElement() : HTMLElement
	{
		return this.shadowRoot?.querySelector("[name='relative']");
	}

	public get value() : {to:string,from:string}|string
	{
		if(this.relative)
		{
			return this.relativeElement?.value || "";
		}
		let val = {
			from: this.fromElement?.findInputField()?.value || null,
			to: this.toElement?.value || null
		}
		if(val.from) val.from = formatDate(parseDate(val.from), {dateFormat:"Y-m-dT00:00:00Z"});
		if(val.to) val.to = formatDate(parseDate(val.to), {dateFormat:"Y-m-dT00:00:00Z"});
		return (val.from || val.to) ? val : null;
	}

	/**
	 * An object with keys 'from' and 'to' for absolute ranges, or a relative range string
	 */
	@property({type: Object, noAccessor: true})
	public set value(new_value : {to:string,from:string}|string)
	{
		if(!this.isConnected)
		{
			this._disconnectedValue = new_value;
			return;
		}
		if(this.relative)
		{
			this.relativeElement.value = new_value;
		}
		else if(this.fromElement && this.toElement)
		{
			// Relative -> absolute
			const range : { from : string | Date, to : string | Date } = typeof new_value == "string" ?
				Et2DateRange.relativeToAbsolute(new_value) : new_value;

			if(this.fromElement._instance?.config?.mode == "range")
			{
				// triggerChange=false: this is a programmatic set (see comment below), same
				// reasoning as Et2Date's own value setter - firing flatpickr's change event here
				// would echo straight back into whatever called this setter (Et2Filterbox ->
				// Et2Nextmatch.applyFilters() -> ... -> here again), the same infinite-loop shape
				// already fixed for Et2Date.clear().
				this.fromElement._instance.setDate([range?.from, range?.to], false);
			}
			else
			{
				this.fromElement.value = typeof range?.from == "string" ? range.from : (range?.from?.toJSON() || "");
				this.toElement.value = typeof range?.to == "string" ? range.to : (range?.to?.toJSON() || "");
			}
		}
	}

	public get absoluteValue() : { to : string | Date, from : string | Date }
	{
		return this.relative ? Et2DateRange.relativeToAbsolute(<string>this.value) : <{
			to : string,
			from : string
		}>this.value;
	}

	/**
	 * Resolve a relative range name ("Last month") into the two dates it covers
	 *
	 * Each entry's from() is handed a copy of the reference day, and its to() is handed a copy of
	 * the from date that was just computed - the end of a range is expressed as an offset from its
	 * own start ("six days later", "the end of that month"), never from today.  Both get their own
	 * copy because the functions mutate what they are given.
	 *
	 * @param date Name of the range, eg. "This week".  Unknown names give an empty range.
	 * @param today Day to calculate from, defaults to the current date.  Only needed for testing.
	 * @returns {{from: Date|string, to: Date|string}} Both empty strings if the name is unknown
	 */
	static relativeToAbsolute(date : string, today? : Date) : { from : Date | string, to : Date | string }
	{
		let absolute = {from: '', to: ''};
		if(!date)
		{
			return absolute;
		}
		let relative = Et2DateRange.relative_dates.find(e => e.value.toLowerCase() == date.toLowerCase());
		if(!relative)
		{
			return absolute;
		}
		let reference = today;
		if(!reference)
		{
			// Midnight today, but as UTC - the range functions all work in UTC
			let tempDate = new Date();
			reference = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(), 0, -tempDate.getTimezoneOffset(), 0);
		}

		const from = relative.from(new Date(reference));
		return {
			from: from,
			to: typeof relative.to == "function" ? relative.to(new Date(from)) : new Date(from)
		};
	}

	// Class Constants
	static readonly relative_dates = [
		// Start and end are relative offsets, see et2_date.set_min()
		// or Date objects
		{
			value: 'Today',
			label: egw.lang ? egw.lang('Today') : 'Today',
			from(date) {return date;},
			to(date) {return date;}
		},
		{
			label: egw.lang ? egw.lang("Yesterday") : "Yesterday",
			value: 'Yesterday',
			from(date) {
				date.setUTCDate(date.getUTCDate() - 1);
				return date;
			},
			to(date) {return date;}
		},
		{
			label: egw.lang ? egw.lang("This week") : "This week",
			value: 'This week',
			from(date) {return egw.week_start(date);},
			to(date) {
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: egw.lang ? egw.lang("Last week") : "Last week",
			value: 'Last week',
			from(date) {
				var d = egw.week_start(date);
				d.setUTCDate(d.getUTCDate() - 7);
				return d;
			},
			to(date) {
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: egw.lang ? egw.lang("This month") : "This month",
			value: 'This month',
			from(date)
			{
				date.setUTCDate(1);
				return date;
			},
			to(date)
			{
				date.setUTCMonth(date.getUTCMonth()+1);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: egw.lang ? egw.lang("Last month") : "Last month",
			value: 'Last month',
			from(date)
			{
				// Day first: changing the month of eg. the 31st overflows into the next one
				date.setUTCDate(1);
				date.setUTCMonth(date.getUTCMonth() - 1);
				return date;
			},
			to(date)
			{
				date.setUTCMonth(date.getUTCMonth()+1);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: egw.lang ? egw.lang("Last 3 months") : "Last 3 months",
			value: 'Last 3 months',
			from(date)
			{
				// Day first: changing the month of eg. the 31st overflows into the next one
				date.setUTCDate(1);
				date.setUTCMonth(date.getUTCMonth() - 2);
				return date;
			},
			to(date)
			{
				date.setUTCMonth(date.getUTCMonth()+3);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: egw.lang ? egw.lang("This year") : "This year",
			value: 'This year',
			from(d) {
				d.setUTCDate(1);
				d.setUTCMonth(0);
				return d;
			},
			to(d) {
				d.setUTCMonth(11);
				d.setUTCDate(31);
				return d;
			}
		},
		{
			label: egw.lang ? egw.lang("Last year") : "Last year",
			value: 'Last year',
			from(d) {
				d.setUTCDate(1);
				d.setUTCMonth(0);
				d.setUTCFullYear(d.getUTCFullYear() - 1);
				return d;
			},
			// d is the from date, which is already in last year
			to(d) {
				d.setUTCMonth(11);
				d.setUTCDate(31);
				return d;
			}
		}
	];
}

customElements.define("et2-date-range", Et2DateRange);