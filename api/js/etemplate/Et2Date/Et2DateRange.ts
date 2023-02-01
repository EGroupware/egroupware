import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {FormControlMixin} from "@lion/form-core";
import {classMap, css, html, ifDefined, LitElement, TemplateResult} from "@lion/core";
import shoelace from "../Styles/shoelace";
import {dateStyles} from "./DateStyles";
import flatpickr from "flatpickr";
import "flatpickr/dist/plugins/rangePlugin";
import {Et2Date, formatDate, parseDate} from "./Et2Date";
import {egw} from "../../jsapi/egw_global";

/**
 * Display a time duration (eg: 3 days, 6 hours)
 *
 * If not specified, the time is in assumed to be minutes and will be displayed with a calculated unit
 * but this can be specified with the properties.
 */
export class Et2DateRange extends Et2InputWidget(FormControlMixin(LitElement))
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			...dateStyles,
			css`

              .input-group {
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: baseline;
              }
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Is the date range relative (this week) or absolute (2016-02-15 - 2016-02-21).  This will affect the value returned.
			 */
			relative: {type: Boolean},

			/**
			 * An object with keys 'from' and 'to' for absolute ranges, or a relative range string
			 */
			value: {type: Object}
		}
	}

	constructor()
	{
		super();
	}

	getUpdateComplete() {
		const p = super.getUpdateComplete();
		if(!this.relative)
		{
			p.then(() => this.setupFlatpickr());
		}
		return p;
	}
	protected setupFlatpickr()
	{
		if(!this.fromElement || !this.fromElement._inputElement) return;

		this.fromElement._instance = flatpickr((<Et2Date>this.fromElement).findInputField(), {
			...(<Et2Date>this.fromElement).getOptions(),
			...{
				plugins: [
					// @ts-ignore ts can't find rangePlugin in IDE
					rangePlugin({
						input: this.toElement
					})
				]
			}
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
                <div class="form-control-input" part="form-control-input">${this._inputGroupTemplate()}</div>
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
                    emptyLabel=${ifDefined(this.emptyLabel)}
                    .select_options=${Et2DateRange.relative_dates}></et2-select>`;
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
				placeholder=${ifDefined(this.placeholder)}
				defaultDate=${ifDefined(this.value?.from)}
			></et2-date>
			<et2-textbox
				name="to"
				?disabled=${this.disabled}
				?readonly=${this.readonly}
				?required=${this.required}
				placeholder=${ifDefined(this.placeholder)}
				value=${ifDefined(this.value?.to)}
			></et2-textbox>
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

	public set value(new_value : {to:string,from:string}|string)
	{
		if(!this.isConnected)
		{
			this.updateComplete.then(() =>
			{
				this.value = new_value;
			});
			return;
		}
		if(this.relative)
		{
			this.relativeElement.value = new_value;
		}
		else if(this.fromElement && this.toElement)
		{
			if(typeof new_value == "string")
			{
				// Relative -> absolute
				new_value = Et2DateRange.relativeToAbsolute(new_value);

			}
			if(this.fromElement._instance)
			{
				this.fromElement._instance.setDate([new_value?.from, new_value?.to], true);
			}
			else
			{
				this.fromElement.value = new_value?.from.toJSON() || "";
				this.toElement.value = new_value?.to.toJSON() || "";
			}
		}
	}

	static relativeToAbsolute(date)
	{
		let absolute = {from: '', to: ''};
		let relative = Et2DateRange.relative_dates.find(e => e.value.toLowerCase() == date.toLowerCase());
		let tempDate = new Date();
		let today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(), 0, -tempDate.getTimezoneOffset(), 0);

		Object.keys(absolute).forEach(k =>
		{
			let value = today.toJSON();
			if(relative && typeof relative[k] == "function")
			{
				absolute[k] = relative[k](new Date(value));
			}
		});

		return absolute;
	}

	// Class Constants
	static readonly relative_dates = [
		// Start and end are relative offsets, see et2_date.set_min()
		// or Date objects
		{
			value: 'Today',
			label: egw.lang('Today'),
			from(date) {return date;},
			to(date) {return date;}
		},
		{
			label: egw.lang('Yesterday'),
			value: 'Yesterday',
			from(date) {
				date.setUTCDate(date.getUTCDate() - 1);
				return date;
			},
			to: ''
		},
		{
			label: egw.lang('This week'),
			value: 'This week',
			from(date) {return egw.week_start(date);},
			to(date) {
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: egw.lang('Last week'),
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
			label: egw.lang('This month'),
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
			label: egw.lang('Last month'),
			value: 'Last month',
			from(date)
			{
				date.setUTCMonth(date.getUTCMonth() - 1);
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
			label: egw.lang('Last 3 months'),
			value: 'Last 3 months',
			from(date)
			{
				date.setUTCMonth(date.getUTCMonth() - 2);
				date.setUTCDate(1);
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
			label: egw.lang('This year'),
			value: 'This year',
			from(d) {
				d.setUTCMonth(0);
				d.setUTCDate(1);
				return d;
			},
			to(d) {
				d.setUTCMonth(11);
				d.setUTCDate(31);
				return d;
			}
		},
		{
			label: egw.lang('Last year'),
			value: 'Last year',
			from(d) {
				d.setUTCMonth(0);
				d.setUTCDate(1);
				d.setUTCYear(d.getUTCYear() - 1);
				return d;
			},
			to(d) {
				d.setUTCMonth(11);
				d.setUTCDate(31);
				d.setUTCYear(d.getUTCYear() - 1);
				return d;
			}
		}
	];
}

customElements.define("et2-date-range", Et2DateRange);