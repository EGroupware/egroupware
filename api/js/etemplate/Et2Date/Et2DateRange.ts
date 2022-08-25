import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {dateStyles} from "./DateStyles";
import {css, html, LitElement, repeat, TemplateResult} from "@lion/core";
import {egw} from "../../jsapi/egw_global";
import {Et2Select} from "../Et2Select/Et2Select";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import "flatpickr/dist/plugins/rangePlugin.js";
import {Et2Date} from "./Et2Date";

export class Et2DateRange extends Et2widgetWithSelectMixin(Et2InputWidget(LitElement))
{
	static get styles()
	{
		return [
			...super.styles,
			dateStyles,
			css`
			:host {
				width: auto;
			}
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * An object with keys 'from' and 'to' for absolute ranges, or a relative range string
			 */
			value: {type: Object},
			/**
			 * Is the date range relative (this week) or absolute (2016-02-15 - 2016-02-21).  This will affect the value returned.
			 */
			relative: {type: Boolean},

			placeholder: {type: String}
		}
	}

	// Class Constants
	static readonly relative_dates = [
		// Start and end are relative offsets, see et2_date.set_min()
		// or Date objects
		{
			value: 'Today',
			label: 'Today',
			from(date) {return date;},
			to(date) {return date;}
		},
		{
			label: 'Yesterday',
			value: 'Yesterday',
			from(date)
			{
				date.setUTCDate(date.getUTCDate() - 1);
				return date;
			},
			to: ''
		},
		{
			label: 'This week',
			value: 'This week',
			from(date) {return egw.week_start(date);},
			to(date)
			{
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: 'Last week',
			value: 'Last week',
			from(date)
			{
				var d = egw.week_start(date);
				d.setUTCDate(d.getUTCDate() - 7);
				return d;
			},
			to(date)
			{
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: 'This month',
			value: 'This month',
			from(date)
			{
				date.setUTCDate(1);
				return date;
			},
			to(date)
			{
				date.setUTCMonth(date.getUTCMonth() + 1);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: 'Last month',
			value: 'Last month',
			from(date)
			{
				date.setUTCMonth(date.getUTCMonth() - 1);
				date.setUTCDate(1);
				return date;
			},
			to(date)
			{
				date.setUTCMonth(date.getUTCMonth() + 1);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: 'Last 3 months',
			value: 'Last 3 months',
			from(date)
			{
				date.setUTCMonth(date.getUTCMonth() - 2);
				date.setUTCDate(1);
				return date;
			},
			to(date)
			{
				date.setUTCMonth(date.getUTCMonth() + 3);
				date.setUTCDate(0);
				return date;
			}
		},
		/*
		'This quarter'=> array(0,0,0,0,  0,0,0,0),      // Just a marker, needs special handling
		'Last quarter'=> array(0,-4,0,0, 0,-4,0,0),     // Just a marker
		*/
		{
			label: 'This year',
			value: 'This year',
			from(d)
			{
				d.setUTCMonth(0);
				d.setUTCDate(1);
				return d;
			},
			to(d)
			{
				d.setUTCMonth(11);
				d.setUTCDate(31);
				return d;
			}
		},
		{
			label: 'Last year',
			value: 'Last year',
			from(d)
			{
				d.setUTCMonth(0);
				d.setUTCDate(1);
				d.setUTCYear(d.getUTCYear() - 1);
				return d;
			},
			to(d)
			{
				d.setUTCMonth(11);
				d.setUTCDate(31);
				d.setUTCYear(d.getUTCYear() - 1);
				return d;
			}
		}
	];


	connectedCallback()
	{
		super.connectedCallback();
		this.updateComplete.then(() =>
		{
			if(!this.relative)
			{
				let options = this._fromNode.getOptions();
				//@ts-ignore rangePlugin is there, really
				options.plugins.push(rangePlugin({input: this._toNode.findInputField()}));
			}
		})
	}

	render()
	{
		if(this.relative)
		{
			return this.relativeTemplate();
		}
		return this.absoluteTemplate();
	}

	protected relativeTemplate()
	{
		return html`
            <et2-select part="relative" empty_label="${this.empty_label}" placeholder="${this.placeholder}"
                        value="${this.value}">
                ${repeat(this.select_options, (d) => d.value, (o) => this._optionTemplate(o))}
            </et2-select>
		`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>` : "";

		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		return html`
            <sl-menu-item value="${option.value}"
                          title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                          class="${option.class}" .option=${option}>
                ${icon}
                ${this.egw().lang(option.label)}
            </sl-menu-item>`;
	}

	protected absoluteTemplate()
	{
		return html`
            <et2-date part="from" id="from"></et2-date>
            <et2-date part="to" id="to"></et2-date>`;
	}

	get select_options() : SelectOption[]
	{
		// @ts-ignore
		const options = super.select_options || [];
		// make sure result is unique

		return [...new Map([...options, ...(Et2DateRange.relative_dates || [])].map(item =>
			[item.value, item])).values()];

	}

	get value() : string | string[] | { from : string; to : string }
	{
		return this.relative ?
			   (this._selectNode?.value || this.__value) :
			{from: <string>this._fromNode?.value, to: <string>this._toNode?.value};
	}

	set value(new_value : string | string[] | { from : string, to : string })
	{
		let oldValue = this.value;
		if(!new_value || new_value == null || typeof new_value == "undefined")
		{
			this._selectNode.value = '';
			this._fromNode.value = null;
			this._toNode.value = null;
		}
		// Relative
		else if(new_value && typeof new_value === 'string')
		{
			this._set_relative_value(new_value);
		}
		else if(new_value && typeof new_value.from === 'undefined' && new_value[0])
		{
			new_value = {
				from: new_value[0],
				to: new_value[1] || ''
			};
		}
		if(new_value && new_value.from && new_value.to)
		{
			this._fromNode._instance.setDate([new_value.from, new_value.to], false);
		}
	}

	_set_relative_value(_value)
	{

		// Show description
		this.__value = _value;
		/*
				let tempDate = new Date();
				let today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(), 0, -tempDate.getTimezoneOffset(), 0);

				// Use strings to avoid references
				this._fromNode.value = today.toJSON();
				this._toNode.value = today.toJSON();

						let relative = null;
						for(var index in Et2DateRange.relative_dates)
						{
							if(Et2DateRange.relative_dates[index].value === _value)
							{
								relative = Et2DateRange.relative_dates[index];
								break;
							}
						}
						if(relative)
						{
							let dates = ["from", "to"];
							let value = today.toJSON();
							for(let i = 0; i < dates.length; i++)
							{
								let date = dates[i];
								if(typeof relative[date] == "function")
								{
									value = relative[date](new Date(value));
								}
								else
								{
									value = this[date]._relativeDate(relative[date]);
								}
								this["_" + date + "Node"].value = value;
							}
						}

				 */
	}

	/**
	 * Get the node where we're putting the options
	 *
	 * @returns {HTMLElement}
	 */
	get _optionTargetNode() : HTMLElement
	{
		return this._selectNode;
	}

	/**
	 * Render select_options as child DOM Nodes
	 * Overridden here because we can do it in the normal way (render())
	 * @protected
	 */
	protected _renderOptions()
	{}

	get _selectNode() : Et2Select
	{
		return this.shadowRoot?.querySelector("[part='relative']");
	}

	get _fromNode() : Et2Date
	{
		return this.shadowRoot?.querySelector("[part='from']")
	}

	get _toNode() : Et2Date
	{
		return this.shadowRoot?.querySelector("[part='to']")
	}
}

customElements.define("et2-date-range", Et2DateRange);