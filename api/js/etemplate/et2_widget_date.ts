/**
 * EGroupware eTemplate2 - Stubs for no longer existing legacy date-widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 */

import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_register_widget, et2_widget, WidgetConfig} from "./et2_core_widget";
import {et2_inputWidget} from './et2_core_inputWidget'
import {date} from "./lib/date.js";

import {Et2Date} from "./Et2Date/Et2Date";
import {Et2DateDuration} from "./Et2Date/Et2DateDuration";
import {Et2DateDurationReadonly} from "./Et2Date/Et2DateDurationReadonly";
import {Et2DateReadonly} from "./Et2Date/Et2DateReadonly";
import {loadWebComponent} from "./Et2Widget/Et2Widget";
import {Et2Select} from "./Et2Select/Et2Select";

/**
 * @deprecated use Et2Date
 */
export class et2_date extends Et2Date {}

/**
 * @deprecated use Et2Date
 */
export class et2_date_duration extends Et2DateDuration {}

/**
 * @deprecated use Et2Date
 */
export class et2_date_duration_ro extends Et2DateDurationReadonly {}

/**
 * @deprecated use Et2Date
 */
export class et2_date_ro extends Et2DateReadonly {}

/**
 * Widget for selecting a date range
 *
 * @todo port to web-component
 */
export class et2_date_range extends et2_inputWidget
{
	static readonly _attributes: any = {
		value: {
			"type": "any",
			"description": "An object with keys 'from' and 'to' for absolute ranges, or a relative range string"
		},
		relative: {
			name: 'Relative',
			type: 'boolean',
			description: 'Is the date range relative (this week) or absolute (2016-02-15 - 2016-02-21).  This will affect the value returned.'
		}
	};

	div: JQuery;
	from: Et2Date;
	to: Et2Date;
	select: Et2Select;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_date_range._attributes, _child || {}));

		this.div = jQuery(document.createElement('div'))
			.attr({	class:'et2_date_range'});

		this.from = null;
		this.to = null;
		this.select = null;

		// Set domid
		this.set_id(this.id);

		this.setDOMNode(this.div[0]);
		this._createWidget();

		this.set_relative(this.options.relative || false);
	}

	_createWidget()
	{
		var widget = this;

		this.from = <Et2Date><any>loadWebComponent('et2-date', {
			id: this.id+'[from]',
			blur: egw.lang('From'),
			onchange(_node,_widget) {
				widget.to.set_min(widget.from.getValue());
				if (_node instanceof Event) widget.onchange.call(widget, _widget, widget);
			}
		}, this);
		this.to = <Et2Date><any>loadWebComponent('et2-date',{
			id: this.id+'[to]',
			blur: egw.lang('To'),
			onchange(_node,_widget) {
				widget.from.set_max(widget.to.getValue());
				if (_node instanceof Event) widget.onchange.call(widget, _widget,widget);
			}
		}, this);
		this.select = <Et2Select><any>loadWebComponent('et2-select',{
			id: this.id+'[relative]',
			select_options: et2_date_range.relative_dates,
			empty_label: this.options.blur || 'All'
		},this);
		//this.select.loadingFinished();
	}

	/**
	 * Function which allows iterating over the complete widget tree.
	 * Overridden here to avoid problems with children when getting value
	 *
	 * @param _callback is the function which should be called for each widget
	 * @param _context is the context in which the function should be executed
	 * @param _type is an optional parameter which specifies a class/interface
	 * 	the elements have to be instanceOf.
	 */
	iterateOver(_callback, _context, _type)
	{
		if (typeof _type == "undefined")
		{
			_type = et2_widget;
		}

		if (this.isInTree() && this.instanceOf(_type))
		{
			_callback.call(_context, this);
		}
	}

	/**
	 * Toggles relative or absolute dates
	 *
	 * @param {boolean} _value
	 */
	set_relative(_value)
	{
		this.options.relative = _value;
		if(this.options.relative)
		{
			jQuery(this.from.getDOMNode()).hide();
			jQuery(this.to.getDOMNode()).hide();
		}
		else
		{
			jQuery(this.select.getDOMNode()).hide();
		}
	}

	set_value(value)
	{
		// @ts-ignore
		if(!value || typeof value == 'null')
		{
			this.select.set_value('');
			this.from.set_value(null);
			this.to.set_value(null);
		}

		// Relative
		if(value && typeof value === 'string')
		{
			this._set_relative_value(value);

		}
		else if(value && typeof value.from === 'undefined' && value[0])
		{
			value = {
				from: value[0],
				to: value[1] || new Date().valueOf()/1000
			};
		}
		else if (value && value.from && value.to)
		{
			this.from.set_value(value.from);
			this.to.set_value(value.to);
		}
	}

	getValue()
	{
		return this.options.relative ?
			this.select.getValue() :
			{ from: this.from.getValue(), to: this.to.getValue() };
	}

	_set_relative_value(_value)
	{
		if(this.options.relative)
		{
			jQuery(this.select.getDOMNode()).show();
		}
		// Show description
		this.select.set_value(_value);

		var tempDate = new Date();
		var today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(),0,-tempDate.getTimezoneOffset(),0);

		// Use strings to avoid references
		this.from.set_value(today.toJSON());
		this.to.set_value(today.toJSON());

		var relative = null;
		for(var index in et2_date_range.relative_dates)
		{
			if(et2_date_range.relative_dates[index].value === _value)
			{
				relative = et2_date_range.relative_dates[index];
				break;
			}
		}
		if(relative)
		{
			var dates = ["from","to"];
			var value = today.toJSON();
			for(var i = 0; i < dates.length; i++)
			{
				var date = dates[i];
				if(typeof relative[date] == "function")
				{
					value = relative[date](new Date(value));
				}
				else
				{
					value = this[date]._relativeDate(relative[date]);
				}
				this[date].set_value(value);
			}
		}
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
		/*
		'This quarter'=> array(0,0,0,0,  0,0,0,0),      // Just a marker, needs special handling
		'Last quarter'=> array(0,-4,0,0, 0,-4,0,0),     // Just a marker
		*/
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
		/* Still needed?
		'2 years ago' => array(-2,0,0,0, -1,0,0,0),
		'3 years ago' => array(-3,0,0,0, -2,0,0,0),
		*/
	];
}
et2_register_widget(et2_date_range, ["date-range"]);